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
use PHPUnit\Util\Exception;
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
        $transactionDeposit = new TransactionDto();
        $transactionDeposit->setId(1);
        $transactionDeposit->setType($this->typesTransaction[2]);
        $transactionDeposit->setCreatedAt('2000-01-22 UTC 00:00:00');
        $transactionDeposit->setAmount(200);
        $transactionDeposit->setCourseCode(null);

        $transactionPayment = new TransactionDto();
        $transactionPayment->setId(2);
        $transactionPayment->setType($this->typesTransaction[1]);
        $transactionPayment->setCreatedAt((new \DateTime())->format('Y-m-d T H:i:s'));
        $transactionPayment->setAmount(50);
        $transactionPayment->setCourseCode('deep_learning');

        $this->historyTransactions = [
            $transactionDeposit,
            $transactionPayment,
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
        throw new Exception();
    }

    public function newCourses(CourseDto $courseDto): ResponseDto
    {
        $responseDto = new ResponseDto();
        if (array_key_exists($courseDto->getCode(), $this->infoCourses)) {
            $responseDto->setCode(500);
            $responseDto->setMessage('Данный код курса уже существует');

            return $responseDto;
        }
        // добавление новоого элемента в список
        $this->infoCourses[$courseDto->getCode()] = [
            'price' => $courseDto->getPrice(),
            'type' => $courseDto->getType(),
        ];
        $responseDto->setSuccess(true);

        return $responseDto;
    }

    public function editCourses(string $codeCourse, CourseDto $courseDto): ResponseDto
    {
        $responseDto = new ResponseDto();
        if (!array_key_exists($codeCourse, $this->infoCourses)) {
            $responseDto->setCode(404);
            $responseDto->setMessage('Курс для изменения не найден');

            return $responseDto;
        }
        if (array_key_exists($courseDto->getCode(), $this->infoCourses)) {
            $responseDto->setCode(500);
            $responseDto->setMessage('Данный код курса уже существует');

            return $responseDto;
        }
        // изменение старого элемента из списка
        unset($this->infoCourses[$codeCourse]);
        $this->infoCourses[$courseDto->getCode()] = [
            'price' => $courseDto->getPrice(),
            'type' => $courseDto->getType(),
        ];

        return $responseDto;
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
                // создаем транзакцию
                $transactionDto = new TransactionDto();
                $transactionDto->setId(1);
                $transactionDto->setType($this->typesTransaction[1]);
                $transactionDto->setCreatedAt((new \DateTime())->format('Y-m-d T H:i:s'));
                $transactionDto->setAmount($price);
                $transactionDto->setCourseCode($codeCourse);
                $this->historyTransactions[] = $transactionDto;

                $payDto = new PayDto();
                $payDto->setSuccess(true);
                $payDto->setCourseType($this->infoCourses[$course->getCode()]['type']);
                $payDto->setExpiresAt($this->getExpiresAt($transactionDto->getCreatedAt(), '7'));

                // отнимаем цену из баланса
                $this->arrUsers[$user->getEmail()]->setBalance($balance - $price);

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
        if ('' === $query) {
            return $this->historyTransactions;
        }
        $filters = explode('&', $query);

        $typesQuery = [];
        $valuesQuery = [];

        foreach ($filters as $filter) {
            $arr = explode('=', $filter);
            $typesQuery[] = $arr[0];
            $valuesQuery[$arr[0]] = $arr[1];
        }
        $responseTransactions = [];

        if (in_array('skip_expired', $typesQuery, true)
            && in_array('type', $typesQuery, true)
            && in_array('course_code', $typesQuery, true)
        ) {
            foreach ($this->historyTransactions as $transaction) {
                $createdAt = $transaction->getCreatedAt();
                if ($valuesQuery['type'] === $transaction->getType()
                    && $this->getExpiresAt($createdAt, '7') > (new \DateTime())->format('Y-m-d T H:i:s')
                    && $valuesQuery['course_code'] === $transaction->getCourseCode()
                ) {
                    $responseTransactions[] = $transaction;
                }
            }

            return $responseTransactions;
        }

        if (in_array('skip_expired', $typesQuery, true)
            && in_array('type', $typesQuery, true)
        ) {
            foreach ($this->historyTransactions as $transaction) {
                $createdAt = $transaction->getCreatedAt();
                if ($valuesQuery['type'] === $transaction->getType()
                    && $this->getExpiresAt($createdAt, '7') > (new \DateTime())->format('Y-m-d T H:i:s')
                ) {
                    $responseTransactions[] = $transaction;
                }
            }

            return $responseTransactions;
        }

        return [];
    }

    private function getExpiresAt(string $dateTime, string $countDay): string
    {
        return (new \DateTime("$dateTime + $countDay day"))->format('Y-m-d T H:i:s');
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
