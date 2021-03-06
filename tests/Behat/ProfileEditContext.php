<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\Profile;
use App\Entity\Region;
use App\Entity\User;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use App\Repository\ProfileRepository;
use App\Repository\RegionRepository;
use App\Repository\UserRepository;
use App\Service\MatchingService;
use App\Service\ProfileService;
use App\Service\UserService;
use App\Tests\Behat\Page\LoginPage;
use App\Tests\Behat\Page\ProfileEditPage;
use App\Tests\Behat\Page\ProfileIndexPage;
use App\Tests\Behat\Page\SearchPage;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use DateTime;
use DateTimeInterface;
use Webmozart\Assert\Assert;

class ProfileEditContext implements Context
{
    private UserService $userService;
    private LoginPage $loginPage;
    private ProfileIndexPage $profileViewPage;
    private ProfileEditPage $profileEditPage;
    private ProfileService $profileService;
    private SearchPage $searchPage;
    private ProfileRepository $profileRepository;
    private MatchingService $matchingService;
    private CityRepository $cityRepository;
    private CountryRepository $countryRepository;
    private RegionRepository $regionRepository;
    private UserRepository $userRepository;

    public function __construct(
        UserService $userService,
        UserRepository $userRepository,
        ProfileRepository $profileRepository,
        ProfileService $profileService,
        MatchingService $matchingService,
        CityRepository $cityRepository,
        RegionRepository $regionRepository,
        CountryRepository $countryRepository,
        LoginPage $loginPage,
        ProfileIndexPage $profileIndexPage,
        ProfileEditPage $profileEditPage,
        SearchPage $searchPage
    ) {
        $this->userService = $userService;
        $this->userRepository = $userRepository;
        $this->profileRepository = $profileRepository;
        $this->profileService = $profileService;
        $this->matchingService = $matchingService;
        $this->loginPage = $loginPage;
        $this->profileViewPage = $profileIndexPage;
        $this->searchPage = $searchPage;
        $this->profileEditPage = $profileEditPage;
        $this->cityRepository = $cityRepository;
        $this->countryRepository = $countryRepository;
        $this->regionRepository = $regionRepository;
    }

    /**
     * @Then I am redirected to the profile edit page
     */
    public function iAmRedirectedToTheProfileEditPage()
    {
        Assert::true($this->profileEditPage->isOpen());
    }

    /**
     * @Given the following profiles exist:
     */
    public function theFollowingProfilesExist(TableNode $table)
    {
        foreach ($table as $row) {
            $user = $this->createProfile(
                $row['email'],
                array_key_exists('age', $row) ? (int) $row['age'] : null,
                array_key_exists('city', $row) ? $row['city'] : null,
                array_key_exists('last_login', $row)
                    ? DateTime::createFromFormat('Y-m-d H:s', $row['last_login']) : new DateTime(),
                array_key_exists('state', $row) ? $row['state'] : null
            );

            if (array_key_exists('requirements', $row)) {
                $this->createRequirements($user, explode(',', $row['requirements']));
            }

            if (array_key_exists('characteristics', $row)) {
                $this->createCharacteristics($user, explode(',', $row['characteristics']));
            }
        }
    }

    private function createProfile(
        string $email,
        ?int $age,
        ?string $city,
        DateTimeInterface $lastLogin,
        ?string $state
    ): User {
        $user = $this->userService->create($email, 'password', true, []);
        $user->setLastLogin($lastLogin ?? new DateTime());
        $this->userRepository->save($user);

        $profile = new Profile();
        if ($age !== null) {
            $profile->setDob((new DateTime())
                ->modify(sprintf('-%d year', $age))
                ->modify('-1 day'));
        }

        if ($city !== null) {
            $profile->setCity($this->getCity($city));
        }

        if ($state !== null) {
            $profile->setState($state);
        }

        $profile->setUsername(str_replace('@example.com', '', $email));
        $profile->setUser($user);

        $this->profileRepository->save($profile);

        return $user;
    }

    private function createCharacteristics(User $user, array $characteristics)
    {
        foreach ($characteristics as $characteristic) {
            $this->matchingService->createCharacteristic($user, trim($characteristic));
        }
    }

    private function createRequirements(User $user, array $requirements)
    {
        foreach ($requirements as $requirement) {
            $this->matchingService->createRequirement($user, trim($requirement));
        }
    }

    private function getCity(string $city): City
    {
        $city = $this->cityRepository->findOneBy([City::NAME => $city]);
        Assert::notNull($city, 'City was null');
        return $city;
    }

    /**
     * @Given I open the my own profile index page
     */
    public function iOpenTheMyOwnProfileViewPage()
    {
        $this->profileViewPage->open();
        Assert::true($this->profileViewPage->isOpen(), 'Profile view page not open');
    }

    /**
     * @When I am on the profile edit page
     */
    public function iAmOnTheProfileEditPage()
    {
        $this->profileEditPage->open();
        Assert::true($this->profileEditPage->isOpen());
    }

    /**
     * @Given the country :country should be displayed
     */
    public function theCountryShouldBeDisplayed($country)
    {
        $this->profileEditPage->assertCountryIsDisplayed($country);
    }

    /**
     * @Given I fill in :username as my username
     */
    public function iFillInAsMyUsername($username)
    {
        $this->profileEditPage->setUsername($username);
    }

    /**
     * @Given I select :regionName as my region
     */
    public function iSelectAsMyRegion($regionName)
    {
        $region = $this->regionRepository->findOneBy([Region::NAME => $regionName]);
        Assert::notNull($region);
        $this->profileEditPage->setRegion($region->getId());
    }

    /**
     * @Given I select :countryName as my country
     */
    public function iSelectAsMyCountry($countryName)
    {
        $country = $this->countryRepository->findOneBy([Country::NAME => $countryName]);
        Assert::notNull($country);
        $this->profileEditPage->setCountry($country->getId());
    }

    /**
     * @Given I select :cityName as my city
     */
    public function iSelectAsMyCity($cityName)
    {
        $city = $this->cityRepository->findOneBy([City::NAME => $cityName]);
        Assert::notNull($city);
        $this->profileEditPage->setCity($city->getId());
    }

    /**
     * @Given I save my profile
     */
    public function iSaveMyProfile()
    {
        $this->profileEditPage->save();
    }

    /**
     * @Then I should see :username as my username
     */
    public function iShouldSeeAsMyUsername(string $username)
    {
        Assert::true($this->profileViewPage->isOpen());
        $this->profileViewPage->assertContains($username);
    }


    private function iFillInMyAbout($about): void
    {
        $this->profileEditPage->setAbout($about);
    }

    private function iFillInMyDob($day, $month, $year): void
    {
        $this->profileEditPage->fillInDob($day, $month, $year);
    }

    private function iFillInMyShape(string $shape): void
    {
        $this->profileEditPage->fillInShape($shape);
    }

    private function iFillInMyColor(string $color): void
    {
        $this->profileEditPage->fillInColor($color);
    }

    /**
     * @Given I fill in my profile with the following details
     */
    public function iFillInMyProfileWithTheFollowingDetails(TableNode $table): void
    {
        foreach ($table as $row) {
            $this->iFillInAsMyUsername($row['username']);
            $this->iSelectAsMyCountry($row['country']);
            $this->iSelectAsMyRegion($row['region']);
            $this->iSelectAsMyCity($row['city']);
            $this->iFillInMyAbout($row['about']);
            $this->iFillInMyDob($row['day'], $row['month'], $row['year']);
            $this->iFillInMyShape($row['shape']);
            $this->iFillInMyColor($row['color']);
        }
    }

    /**
     * @Then I should see the following profile details
     */
    public function iShouldSeeTheFollowingProfileDetails(TableNode $table)
    {
        foreach ($table as $row) {
            $this->profileEditPage->assertContains($row['username']);
            $this->profileEditPage->assertContains($row['region']);
            $this->profileEditPage->assertContains($row['city']);
            $this->profileEditPage->assertContains($row['about']);
            $this->profileEditPage->assertContains(DateTime::createFromFormat(
                'j-n-Y',
                sprintf('%d-%d-%d', $row['day'], $row['month'], $row['year'])
            )
                    ->diff(new DateTime())
                    ->format('%Y'));
            $this->profileEditPage->assertContains($row['color']);
            $this->profileEditPage->assertContains($row['shape']);
        }
    }
}
