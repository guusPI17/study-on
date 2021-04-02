<?php

namespace App\Service;

use App\DTO\Response as ResponseDto;
use App\DTO\User as UserDto;
use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Security\User;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BillingClient
{
    private $billingUrlBase;
    private $billingApiVersion;
    private $httpClient;
    private $serializer;
    private $security;

    public function __construct(
        string $billingUrlBase,
        string $billingApiVersion,
        HttpClientInterface $httpClient,
        SerializerInterface $serializer,
        Security $security
    ) {
        $this->billingUrlBase = $billingUrlBase;
        $this->billingApiVersion = $billingApiVersion;
        $this->httpClient = $httpClient;
        $this->serializer = $serializer;
        $this->security = $security;
    }

    public function refreshToken(UserDto $dataUser): UserDto
    {
        $dataSerialize = $this->serializer->serialize($dataUser, 'json');
        $headers['Content-Type'] = 'application/json';
        $response = $this->apiRequest('/token/refresh', 'POST', $headers, $dataSerialize);

        /** @var UserDto $userDto */
        $userDto = $this->serializer->deserialize($response, UserDto::class, 'json');

        return $userDto;
    }

    public function current(): UserDto
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $headers['Content-Type'] = 'application/json';
        if ($user) {
            $headers['Authorization'] = 'Bearer ' . $user->getApiToken();
        }
        $response = $this->apiRequest('/users/current', 'GET', $headers);

        return $this->serializer->deserialize($response, UserDto::class, 'json');
    }

    public function registration(UserDto $dataUser): UserDto
    {
        $dataSerialize = $this->serializer->serialize($dataUser, 'json');
        $headers['Content-Type'] = 'application/json';
        $response = $this->apiRequest('/register', 'POST', $headers, $dataSerialize);

        /** @var UserDto $userDto */
        $userDto = $this->serializer->deserialize($response, UserDto::class, 'json');

        return $userDto;
    }

    public function authorization(UserDto $dataUser): UserDto
    {
        $dataSerialize = $this->serializer->serialize($dataUser, 'json');
        $headers['Content-Type'] = 'application/json';
        $response = $this->apiRequest('/auth', 'POST', $headers, $dataSerialize);

        /** @var UserDto $userDto */
        $userDto = $this->serializer->deserialize($response, UserDto::class, 'json');

        return $userDto;
    }

    private function apiRequest(string $endUrl, string $method, array $headers = [], string $body = null): string
    {
        $url = "$this->billingUrlBase/$this->billingApiVersion$endUrl";
        $messageError = 'Сервис временно недоступен. Попробуйте позднее.';
        try {
            $response = $this->httpClient->request(
                $method,
                $url,
                [
                    'headers' => $headers,
                    'body' => $body,
                ]
            );
            $content = $response->getContent(false);
            $status = $response->getStatusCode();
            if (!in_array($status, [200, 201], true)) {
                $error = $this->serializer->deserialize($content, ResponseDto::class, 'json');
                throw new FailureResponseException($error);
            }

            return $content;
        } catch (TransportExceptionInterface $e) {
            throw new BillingUnavailableException($messageError);
        } catch (ClientExceptionInterface $e) {
            throw new BillingUnavailableException($messageError);
        } catch (RedirectionExceptionInterface $e) {
            throw new BillingUnavailableException($messageError);
        } catch (ServerExceptionInterface $e) {
            throw new BillingUnavailableException($messageError);
        }
    }
}
