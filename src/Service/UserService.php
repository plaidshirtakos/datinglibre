<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Email;
use App\Entity\Token;
use App\Entity\User;
use App\Repository\UserRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\Criteria;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserService
{
    private UserRepository $userRepository;
    private EmailService $emailService;
    private TokenService $tokenService;
    private UserPasswordEncoderInterface $passwordEncoder;
    private ProfileService $profileService;
    private TranslatorInterface $translator;
    private string $adminEmail;

    public function __construct(
        UserRepository $userRepository,
        EmailService $emailService,
        TokenService $tokenService,
        ProfileService $profileService,
        UserPasswordEncoderInterface $passwordEncoder,
        TranslatorInterface $translator,
        string $adminEmail
    ) {
        $this->userRepository = $userRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->profileService = $profileService;
        $this->emailService = $emailService;
        $this->tokenService = $tokenService;
        $this->adminEmail = $adminEmail;
        $this->translator = $translator;
    }

    public function create(
        string $email,
        string $password,
        bool $enabled,
        array $roles
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordEncoder->encodePassword($user, $password));
        $user->setRoles($roles);
        $user->setEnabled($enabled);

        return $this->userRepository->save($user);
    }

    public function delete(?UuidInterface $deletedById, UuidInterface $userId)
    {
        $this->profileService->delete($userId);
        $this->userRepository->delete($userId);
    }

    public function deleteByPassword(?UuidInterface $userId, string $password)
    {
        $user = $this->userRepository->find($userId);

        if ($user === null) {
            return false;
        }

        if ($this->passwordEncoder->isPasswordValid($user, $password)) {
            $this->delete(null, $user->getId());
            return true;
        } else {
            return false;
        }
    }

    public function signup(User $newUser, string $password): void
    {
        $existingUser = $this->findByEmail($newUser->getEmail());

        if (null !== $existingUser) {
            $email = (new TemplatedEmail())
                ->from($this->adminEmail)
                ->subject($this->translator->trans('registration.already_exists_subject'))
                ->to($newUser->getEmail())
                ->htmlTemplate('user/email/already_exists.html.twig');

            $this->emailService->send($email, $existingUser, Email::ALREADY_EXISTS);
            return;
        }

        $newUser->setPassword($this->passwordEncoder->encodePassword($newUser, $password));
        $savedUser = $this->userRepository->save($newUser);

        $token = $this->tokenService->save($savedUser, Token::SIGNUP);

        $email = (new TemplatedEmail())
            ->from($this->adminEmail)
            ->subject($this->translator->trans('user.signup_subject'))
            ->to($savedUser->getEmail())
            ->htmlTemplate('user/email/confirm.html.twig')
            ->context(['secret' => $token->getSecret(), 'userId' => $savedUser->getId()->toString()]);

        $this->emailService->send($email, $savedUser, Email::SIGNUP);
    }

    public function updatePassword(string $userId, string $secret, string $newPassword): bool
    {
        $user = $this->userRepository->find($userId);

        if ($user === null) {
            return false;
        }

        if ($this->tokenService->verify($user, $secret, Token::PASSWORD_RESET)) {
            $user->setPassword($this->passwordEncoder->encodePassword($user, $newPassword));
            $this->userRepository->save($user);
            return true;
        }

        return false;
    }

    public function resetPassword(string $email): void
    {
        $user = $this->userRepository->findOneBy([User::EMAIL => $email]);

        if ($user == null) {
            return;
        }

        $token = $this->tokenService->save($user, Token::PASSWORD_RESET);

        $email = (new TemplatedEmail())
            ->from($this->adminEmail)
            ->subject($this->translator->trans('user.reset_password_subject'))
            ->to($user->getEmail())
            ->htmlTemplate('user/email/password_reset.html.twig')
            ->context(['secret' => $token->getSecret(), 'userId' => $user->getId()->toString()]);

        $this->emailService->send($email, $user, Email::PASSWORD_RESET);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->userRepository->findOneBy([User::EMAIL => $email]);
    }

    public function enable(string $userId, $secret): bool
    {
        if (empty($userId)) {
            return false;
        }

        $user = $this->userRepository->find($userId) ;

        if ($user == null) {
            return false;
        }

        if ($this->tokenService->verify($user, $secret, Token::SIGNUP)) {
            $user->setEnabled(true);
            $this->userRepository->save($user);
            return true;
        }

        return false;
    }

    public function purge(string $type, int $hours): void
    {
        $users = [];
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $interval = new DateInterval(sprintf('PT%dH', $hours));

        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()
                ->lt('createdAt', $now->sub($interval)));

        switch ($type) {
            case 'ALL':
                $users = $this->userRepository->matching($criteria);
                break;
            case 'NOT_ENABLED':
                $criteria->andWhere(Criteria::expr()->eq(User::ENABLED, false));
                $users = $this->userRepository->matching($criteria);
                break;
        }

        /** @var User $user */
        foreach ($users as $user) {
            $this->delete(null, $user->getId());
        }
    }
}
