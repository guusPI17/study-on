<?php

namespace App\Controller;

use App\DTO\User as UserDto;
use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    private $billingClient;

    public function __construct(BillingClient $billingClient)
    {
        $this->billingClient = $billingClient;
    }

    /**
     * @Route("/profile", name="user_profile")
     */
    public function profile(): Response
    {
        try {
            /** @var UserDto $userDto */
            $userDto = $this->billingClient->current();
        } catch (BillingUnavailableException $e) {
            return $this->render('errors/error.html.twig', [
                'error' => $e->getMessage(),
            ]);
        } catch (FailureResponseException $e) {
            return $this->render('errors/error.html.twig', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->render('user/profile.html.twig', [
            'user' => $userDto,
        ]);
    }
}
