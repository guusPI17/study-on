<?php

namespace App\Controller;


use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Form\RegistrationFormType;
use App\Security\Authenticator;
use App\DTO\User as UserDto;
use App\Security\User;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/register", name="app_register")
     */
    public function register(
        Request $request,
        GuardAuthenticatorHandler $guardHandler,
        Authenticator $authenticator,
        BillingClient $billingClient
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('user_profile');
        }

        $userDto = new UserDto();
        $form = $this->createForm(RegistrationFormType::class, $userDto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = User::fromDto($billingClient->registration($userDto));

            } catch (FailureResponseException $e) {
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                    'errors' => $e->getErrors(),
                ]);

            } catch (BillingUnavailableException $e) {
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                    'errors' => $e->getMessage(),
                ]);

            }

            // do anything else you need here, like send an email

            return $guardHandler->authenticateUserAndHandleSuccess(
                $user,
                $request,
                $authenticator,
                'main' // firewall name in security.yaml
            );
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
