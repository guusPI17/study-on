<?php

namespace App\Controller;

use App\DTO\Transaction as TransactionDto;
use App\DTO\User as UserDto;
use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Repository\CourseRepository;
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
            throw new \Exception($e->getMessage());
        } catch (FailureResponseException $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->render(
            'user/profile.html.twig',
            ['user' => $userDto]
        );
    }

    /**
     * @Route("/transactions", name="user_transactions")
     */
    public function transactions(CourseRepository $courseRepository): Response
    {
        try {
            $courses = $courseRepository->findAll();
            /** @var TransactionDto[] $transactionsDto */
            $transactionsDto = $this->billingClient->transactionHistory();
            foreach ($transactionsDto as &$transaction) {
                if ($transaction->getCourseCode()) {
                    $key = array_search($transaction->getCourseCode(), array_column($courses, 'code'), true);
                    $id = $courses[$key]->getId();
                    $transaction->setId($id);
                }
            }
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        } catch (FailureResponseException $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->render(
            'user/transactions.html.twig',
            ['transactions' => $transactionsDto]
        );
    }
}
