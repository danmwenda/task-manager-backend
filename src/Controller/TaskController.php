<?php

namespace App\Controller;

use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/api/tasks')]
class TaskController extends AbstractController
{
    #[Route('', name: 'create_task', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $task = new Task();

        $task->setTitle($data['title'] ?? '');
        $task->setDescription($data['description'] ?? '');
        $task->setIsDone(false);
        $task->setCreatedAt(new \DateTimeImmutable());
        $task->setUser($this->getUser());

        $errors = $validator->validate($task);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], 400);
        }

        $em->persist($task);
        $em->flush();

        return new JsonResponse(['message' => 'Task created'], 201);
    }

    #[Route('', name: 'list_tasks', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ): JsonResponse {
        $user = $this->getUser();
        $queryBuilder = $em->getRepository(Task::class)->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user);

        // Pagination logic
        $pagination = $paginator->paginate(
            $queryBuilder, 
            $request->query->getInt('page', 1), 
            $request->query->getInt('limit', 10)
        );

        $tasks = [];
        foreach ($pagination as $task) {
            $tasks[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'description' => $task->getDescription(),
                'isDone' => $task->getIsDone(),
                'createdAt' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse([
            'data' => $tasks,
            'total' => $pagination->getTotalItemCount(),
            'page' => $pagination->getCurrentPageNumber(),
            'pages' => $pagination->getPageCount(),
        ]);
    }

    #[Route('/{id}', name: 'get_task', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getTask(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $task = $em->getRepository(Task::class)->find($id);

        if (!$task || $task->getUser() !== $user) {
            return new JsonResponse(['error' => 'Task not found or access denied'], 404);
        }

        return new JsonResponse([
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'isDone' => $task->getIsDone(),
            'createdAt' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}', name: 'update_task', methods: ['PUT'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getUser();
        $task = $em->getRepository(Task::class)->find($id);

        if (!$task || $task->getUser() !== $user) {
            return new JsonResponse(['error' => 'Task not found or access denied'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $task->setTitle($data['title'] ?? $task->getTitle());
        $task->setDescription($data['description'] ?? $task->getDescription());
        $task->setIsDone($data['isDone'] ?? $task->getIsDone());

        $errors = $validator->validate($task);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], 400);
        }

        $em->flush();
        return new JsonResponse(['message' => 'Task updated'], 200);
    }

    #[Route('/{id}', name: 'patch_task', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function patch(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getUser();
        $task = $em->getRepository(Task::class)->find($id);

        if (!$task || $task->getUser() !== $user) {
            return new JsonResponse(['error' => 'Task not found or access denied'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) {
            $task->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $task->setDescription($data['description']);
        }
        if (isset($data['isDone'])) {
            $task->setIsDone($data['isDone']);
        }

        $errors = $validator->validate($task);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], 400);
        }

        $em->flush();
        return new JsonResponse(['message' => 'Task partially updated'], 200);
    }

    #[Route('/{id}', name: 'delete_task', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $task = $em->getRepository(Task::class)->find($id);

        if (!$task || $task->getUser() !== $user) {
            return new JsonResponse(['error' => 'Task not found or access denied'], 404);
        }

        $em->remove($task);
        $em->flush();

        return new JsonResponse(['message' => 'Task deleted'], 200);
    }

    #[Route('/{id}/complete', name: 'complete_task', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function complete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $task = $em->getRepository(Task::class)->find($id);

        if (!$task || $task->getUser() !== $user) {
            return new JsonResponse(['error' => 'Task not found or access denied'], 404);
        }

        // Mark task as completed
        $task->setIsDone(true);
        $em->flush();

        return new JsonResponse(['message' => 'Task marked as completed'], 200);
    }
}
