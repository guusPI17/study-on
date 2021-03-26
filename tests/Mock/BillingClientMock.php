<?php

namespace App\Tests\Mock;

use App\DTO\Response as ResponseDto;
use App\DTO\User as UserDto;
use App\Exception\FailureResponseException;
use App\Security\User;
use App\Service\BillingClient;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BillingClientMock extends BillingClient
{
    private $security;
    private $arrUsers;
    private $serializer;

    public function __construct(
        string $billingUrlBase,
        string $billingApiVersion,
        HttpClientInterface $httpClient,
        SerializerInterface $serializer,
        Security $security
    ) {
        parent::__construct($billingUrlBase, $billingApiVersion, $httpClient, $serializer, $security);

        $user = new UserDto();
        $user->setUsername('user@test.com');
        $user->setPassword('user@test.com');
        $user->setToken('header.eyJpYXQiOjE2MTY2ODA4MDIsImV4cCI6MTYxNjY4NDQwM
        iwicm9sZXMiOlsiUk9MRV9VU0VSIl0sInVzZXJuYW1lIjoidXNlckB0ZXN0LmNvbSJ9.signature');
        $user->setRoles(['ROLE_USER']);
        $user->setBalance(100);

        $admin = new UserDto();
        $admin->setUsername('admin@test.com');
        $admin->setPassword('admin@test.com');
        $admin->setToken('header.eyJpYXQiOjE2MTY2ODEzMTEsImV4cCI6MTYxNjY4NDkxMSwicm9sZXMiOls
        iUk9MRV9TVVBFUl9BRE1JTiIsIlJPTEVfVVNFUiJdLCJ1c2VybmFtZSI6ImFkbWluQHRlc3QuY29tIn0.signature');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setBalance(0);

        $this->arrUsers = [$user, $admin];

        $this->security = $security;
        $this->serializer = $serializer;
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
        $dataPayload = [
            'username' => $user->getUsername(),
            'roles' => ['ROLE_USER'],
        ];
        $payload = base64_encode(json_encode($dataPayload));
        $newUser->setToken('header.' . $payload . '.signature');
        $newUser->setBalance(0);
        $newUser->setRoles(['ROLE_USER']);

        return $newUser;
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
}
