<?php

namespace App\Controller;

use App\Entity\Task;
use App\Pagination\PaginatedCollection;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class TaskController
 * @package App\Controller
 * @Route("/api", name="task_api")
 */
class TaskController extends AbstractController
{
    /**
     * @param Request $request
     * @param ValidatorInterface $validator
     * @param TaskRepository $taskRepository
     * @return JsonResponse
     * @Route("/tasks", name="tasks", methods={"GET"})
     */
    public function getPosts(Request $request, ValidatorInterface $validator, TaskRepository $taskRepository){

        $page = (int)$request->query->get('page', 1);
        $perPage = (int)$request->query->get('per_page', 20);

        $constraint = new Assert\Collection([
            'page' =>  new Assert\Positive(),
            'per_page' => new Assert\Range(['min' => 1, 'max' => 100]),
        ]);

        $validationResult = $validator->validate(
            [
                'page' => $page,
                'per_page' => $perPage
            ],
            $constraint
        );

        if (0 !== count($validationResult)) {
            $errorsString = (string) $validationResult;
            return $this->json(['errors' => $errorsString], 500);
        }

        $qb = $taskRepository
            ->findAllQueryBuilder();

        $adapter = new DoctrineORMAdapter($qb);
        $pf = new Pagerfanta($adapter);
        $pf->setMaxPerPage($perPage);
        $pf->setCurrentPage($page);

        $tasks = [];
        foreach ($pf->getCurrentPageResults() as $result) {
            $tasks[] = $result;
        }

        $paginatedCollection = new PaginatedCollection(
            $tasks,
            $pf->getNbResults(),
            $page,
            $perPage
        );

        return $this->json($paginatedCollection, 200);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param TaskRepository $taskRepository
     * @return JsonResponse
     * @throws \Exception
     * @Route("/tasks", name="tasks_add", methods={"POST"})
     */
    public function addPost(Request $request, ValidatorInterface $validator, EntityManagerInterface $entityManager, TaskRepository $taskRepository){

        try{
            $request = $this->transformJsonBody($request);

            $constraint = new Assert\Collection([
                'name' =>  [
                    new Assert\Length(['min' => 1, 'max' => 255]),
                    new Assert\NotBlank()
                ],
                'description' => [
                    new Assert\Length(['min' => 1, 'max' => 255]),
                    new Assert\NotBlank()
                ],
            ]);

            $validationResult = $validator->validate(
                [
                    'name' => $request->get('name'),
                    'description' => $request->get('description')
                ],
                $constraint
            );

            if (0 !== count($validationResult)) {
                $errorsString = (string) $validationResult;
                return $this->json(['errors' => $errorsString], 500);
            }

            $task = new Task();
            $task->setName($request->get('name'));
            $task->setDescription($request->get('description'));
            $entityManager->persist($task);
            $entityManager->flush();

            return $this->json(['success' => "Task added successfully"], 200);

        }catch (\Exception $e){
            return $this->json(['errors' => "Data is not valid"], 422);
        }

    }

    protected function transformJsonBody(\Symfony\Component\HttpFoundation\Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return $request;
        }

        $request->request->replace($data);

        return $request;
    }
}
