<?php

namespace App\Controller;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\SecurityBundle\Security;


class LoginController extends AbstractController
{
    private $jwtManager;
    private $passwordHasher;
    private $security;

    public function __construct(JWTTokenManagerInterface $jwtManager, UserPasswordHasherInterface $passwordHasher, Security $security)
    {
        $this->jwtManager = $jwtManager;
        $this->passwordHasher = $passwordHasher;
        $this->security = $security;
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        // Validate the data
        if (!$email || !$password) {
            return new JsonResponse(['error' => 'Missing required fields.'], 400);
        }

        // Find user by email
        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found.'], 400);
        }

        // Validate password
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['error' => 'Invalid password.'], 400);
        }

        // Generate JWT Token
        $token = $this->jwtManager->create($user);

        // Return the token
        return new JsonResponse(['token' => $token], 200);
    }
}
