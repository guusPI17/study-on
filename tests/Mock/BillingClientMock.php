<?php

namespace App\Tests\Mock;

use App\DTO\Course as CourseDto;
use App\DTO\Pay as PayDto;
use App\DTO\Response as ResponseDto;
use App\DTO\Transaction as TransactionDto;
use App\DTO\User as UserDto;
use App\Entity\Course;
use App\Exception\FailureResponseException;
use App\Security\User;
use App\Service\BillingClient;
use Doctrine\Bundle\DoctrineBundle\Registry;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BillingClientMock extends BillingClient
{
    private $security;
    private $arrUsers;
    private $doctrine;
    private $typesCourse;
    private $typesTransaction;
    private $infoCourses;
    private $historyTransactions;

    public function __construct(
        Registry $doctrine,
        string $billingUrlBase,
        string $billingApiVersion,
        HttpClientInterface $httpClient,
        SerializerInterface $serializer,
        Security $security,
        array $arrUsers = []
    ) {
        parent::__construct($billingUrlBase, $billingApiVersion, $httpClient, $serializer, $security);

        $this->arrUsers = $arrUsers;

        $this->security = $security;
        $this->doctrine = $doctrine;

        $this->typesCourse = [
            1 => 'rent',
            2 => 'free',
            3 => 'buy',
        ];

        $this->typesTransaction = [
            1 => 'payment',
            2 => 'deposit',
        ];

        $this->infoCourses = [
            'deep_learning' => [
                'price' => 50,
                'type' => 'rent',
            ],
            'statistics_course' => [
                'price' => 30,
                'type' => 'rent',
            ],
            'c_sharp_course' => [
                'price' => 250,
                'type' => 'buy',
            ],
            'python_course' => [
                'price' => 0,
                'type' => 'free',
            ],
            'design_course' => [
                'price' => 70,
                'type' => 'buy',
            ],
        ];
        $transactionDtoStarting = new TransactionDto();
        $transactionDtoStarting->setId(1);
        $transactionDtoStarting->setType($this->typesTransaction[2]);
        $transactionDtoStarting->setCreatedAt('2000-01-15');
        $transactionDtoStarting->setAmount(200);
        $transactionDtoStarting->setCourseCode(null);

        $this->historyTransactions = [
            $transactionDtoStarting,
        ];
    }

    public function refreshToken(UserDto $dataUser): UserDto
    {
        foreach ($this->arrUsers as $user) {
            if ($user->getRefreshToken() == $dataUser->getRefreshToken()) {
                $userDto = new UserDto();
                $userDto->setRefreshToken($user->getRefreshToken());
                $userDto->setToken($user->getToken());

                return $userDto;
            }
        }
    }

    public function payCourse(string $codeCourse): PayDto
    {
        $courseRepository = $this->doctrine->getRepository(Course::class);
        $course = $courseRepository->findOneBy(['code' => $codeCourse]);
        // если курс существует
        if ($course) {
            // получаем цену курса
            $price = $this->infoCourses[$course->getCode()]['price'];

            /** @var User $user */
            $user = $this->security->getUser();

            // находим баланс у пользователя за которым сидим
            $balance = $this->arrUsers[$user->getEmail()]->getBalance();

            // если баланс позволяет купить
            if ($balance >= $price) {
                $payDto = new PayDto();
                $payDto->setSuccess(true);
                $payDto->setCourseType($this->infoCourses[$course->getCode()]['type']);
                $payDto->setExpiresAt((new \DateTime('+ 7 day'))->format('Y-m-d T H:i:s'));

                // отнимаем цену из баланса
                $this->arrUsers[$user->getEmail()]->setBalance($balance - $price);

                // создаем транзакцию
                $transactionDto = new TransactionDto();
                $transactionDto->setId(1);
                $transactionDto->setType($this->typesTransaction[1]);
                $transactionDto->setCreatedAt((new \DateTime())->format('Y-m-d T H:i:s'));
                $transactionDto->setAmount($price);
                $transactionDto->setCourseCode($codeCourse);
                $this->historyTransactions[] = $transactionDto;

                return $payDto;
            }
            $responseDto = new ResponseDto();
            $responseDto->setCode(406);
            $responseDto->setMessage('На вашем счету недостаточно средств');
            throw new FailureResponseException($responseDto);
        }
        $responseDto = new ResponseDto();
        $responseDto->setCode(404);
        $responseDto->setMessage('Данный курс не найден');
        throw new FailureResponseException($responseDto);
    }

    public function listCourses(): array
    {
        $courseRepository = $this->doctrine->getRepository(Course::class);
        $courses = $courseRepository->findAll();
        $coursesDto = [];
        foreach ($courses as $course) {
            $courseDto = new CourseDto();
            $courseDto->setType($this->infoCourses[$course->getCode()]['type']);
            $courseDto->setPrice($this->infoCourses[$course->getCode()]['price']);
            $courseDto->setCode($course->getCode());
            $coursesDto[] = $courseDto;
        }

        return $coursesDto;
    }

    public function oneCourse(string $codeCourse): CourseDto
    {
        $courseRepository = $this->doctrine->getRepository(Course::class);
        $course = $courseRepository->findOneBy(['code' => $codeCourse]);

        if ($course) {
            $courseDto = new CourseDto();
            $courseDto->setType($this->infoCourses[$course->getCode()]['type']);
            $courseDto->setPrice($this->infoCourses[$course->getCode()]['price']);
            $courseDto->setCode($course->getCode());

            return $courseDto;
        }
        $responseDto = new ResponseDto();
        $responseDto->setCode(404);
        $responseDto->setMessage('Данный курс не найден');
        throw new FailureResponseException($responseDto);
    }

    public function transactionHistory(string $query = ''): array
    {
        return $this->historyTransactions;
    }

    public function current(): UserDto
    {
        /** @var User $user */
        $user = $this->security->getUser();

        /** @var UserDto $userDto */
        foreach ($this->arrUsers as $userDto) {
            if ($userDto->getToken() == $user->getApiToken()) {
                return $userDto;
            }
        }
        $responseDto = new ResponseDto();
        $responseDto->setCode(401);
        $responseDto->setMessage('JWT Token not found');
        throw new FailureResponseException($responseDto);
    }

    public function registration(UserDto $user): UserDto
    {
        /** @var UserDto $userDto */
        foreach ($this->arrUsers as $userDto) {
            if ($userDto->getUsername() == $user->getUsername()) {
                $responseDto = new ResponseDto();
                $responseDto->setCode(400);
                $responseDto->getError(['Данная почта уже зарегистрированна.']);
                throw new FailureResponseException($responseDto);
            }
        }
        $newUser = new UserDto();
        $newUser->setUsername($user->getUsername());
        $newUser->setPassword($user->getPassword());
        $newUser->setRefreshToken($user->getPassword());
        $dataPayload = [
            'username' => $user->getUsername(),
            'roles' => ['ROLE_USER'],
            'exp' => (new \DateTime('+ 1 hour'))->getTimestamp(),
        ];
        $payload = base64_encode(json_encode($dataPayload));
        $newUser->setToken('header.' . $payload . '.signature');
        $newUser->setBalance(200);
        $newUser->setRoles(['ROLE_USER']);
        $this->arrUsers[] = $newUser;

        return $newUser;
    }

    public function authorization(UserDto $user): UserDto
    {
        /** @var UserDto $userDto */
        foreach ($this->arrUsers as $userDto) {
            if ($userDto->getUsername() == $user->getUsername()
                && $userDto->getPassword() == $user->getPassword()
            ) {
                return $userDto;
            }
        }
        $responseDto = new ResponseDto();
        $responseDto->setCode(401);
        $responseDto->setMessage('Invalid credentials.');
        throw new FailureResponseException($responseDto);
    }
}
