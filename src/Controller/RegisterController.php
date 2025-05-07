<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Profile;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RegisterController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
public function register(
    Request $request,
    UserPasswordHasherInterface $passwordHasher,
    EntityManagerInterface $em,
    MailerInterface $mailer
): JsonResponse {
    $data = json_decode($request->getContent(), true);

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;
    $firstName = $data['firstName'] ?? null;
    $lastName = $data['lastName'] ?? null;
    $bio = $data['bio'] ?? null;

    if (!$email || !$password || !$firstName || !$lastName) {
        return new JsonResponse(['error' => 'Missing required fields.'], 400);
    }

    if ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
        return new JsonResponse(['error' => 'User already exists.'], 400);
    }

    $user = new User();
    $user->setEmail($email);
    $user->setCreatedAt(new DateTime());
    $user->setRoles(['ROLE_USER']);
    $user->setPassword($passwordHasher->hashPassword($user, $password));

    $profile = new Profile();
    $profile->setFirstName($firstName)
        ->setLastName($lastName)
        ->setBio($bio);

    $user->setProfile($profile);
    $profile->setUser($user);

    $verificationCode = rand(100000, 999999);
    $user->setVerificationCode($verificationCode);

    $emailMessage = (new Email())
        ->from('tech@selekta.cc')
        ->to($email)
        ->subject('Please verify your email address')
        ->html("<p>Your verification code is: <strong>{$verificationCode}</strong></p>");

    try {
        $mailer->send($emailMessage);

        // Only persist after email is sent successfully
        $em->persist($user);
        $em->persist($profile);
        $em->flush();
    } catch (\Exception $e) {
        return new JsonResponse(['error' => 'Failed to send verification email.'], 500);
    }

    return new JsonResponse(['message' => 'User registered successfully. Please verify your email.'], 201);
}

    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $code = $data['code'] ?? null;

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || $user->getVerificationCode() !== (int)$code) {
            return new JsonResponse(['error' => 'Invalid verification code.'], 400);
        }

        $user->setVerificationCode(null);
        $em->flush();

        return new JsonResponse(['message' => 'Email verified successfully.']);
    }

    #[Route('/api/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request, EntityManagerInterface $em, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found.'], 404);
        }

        $resetCode = rand(100000, 999999);
        $user->setVerificationCode($resetCode);
        $em->flush();

        $emailMessage = (new Email())
            ->from('tech@selekta.cc')
            ->to($email)
            ->subject('Reset your password')
            ->html("<p>Your password reset code is: <strong>{$resetCode}</strong></p>");

        $mailer->send($emailMessage);

        return new JsonResponse(['message' => 'Password reset code sent.']);
    }

    #[Route('/api/change-password', name: 'api_change_password', methods: ['POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $code = $data['code'] ?? null;
        $newPassword = $data['newPassword'] ?? null;

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || $user->getVerificationCode() !== (int)$code) {
            return new JsonResponse(['error' => 'Invalid verification code.'], 400);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $user->setVerificationCode(null);
        $em->flush();

        return new JsonResponse(['message' => 'Password changed successfully.']);
    }

    #[Route('/api/profile/{id}', name: 'api_get_profile', methods: ['GET'])]
    public function getProfile(Profile $profile): JsonResponse
    {
        return $this->json([
            'firstName' => $profile->getFirstName(),
            'lastName' => $profile->getLastName(),
            'bio' => $profile->getBio(),
            'profilePicture' => $profile->getProfilePicture()
        ]);
    }

    #[Route('/api/profile/{id}', name: 'api_update_profile', methods: ['PUT'])]
    public function updateProfile(Request $request, Profile $profile, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $profile->setFirstName($data['firstName'] ?? $profile->getFirstName())
            ->setLastName($data['lastName'] ?? $profile->getLastName())
            ->setBio($data['bio'] ?? $profile->getBio());

        $em->flush();

        return new JsonResponse(['message' => 'Profile updated successfully.']);
    }

    #[Route('/api/profile/{id}', name: 'api_delete_profile', methods: ['DELETE'])]
    public function deleteProfile(Profile $profile, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($profile);
        $em->flush();

        return new JsonResponse(['message' => 'Profile deleted.']);
    }
}
