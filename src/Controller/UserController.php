<?php

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use App\Repository\UserRepository;
use App\Repository\ClientsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @Route("/api/user", name="api_user")
 */
class UserController extends AbstractController
{
    private $entityManager;
    private $passwordHasher;
    private $jwtEncoder;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        JWTEncoderInterface $jwtEncoder
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->jwtEncoder = $jwtEncoder;
    }

    /**
     * @Route("/register", name="user_register", methods={"POST"})
     */
    public function register(ClientsRepository $clientRepository, SerializerInterface $serializer, ManagerRegistry $doctrine, Request $request, UserPasswordHasherInterface $passwordHasher, ValidatorInterface $validator): JsonResponse
    {
        $em = $doctrine->getManager();
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');
        $user->setRoles(['ROLE_USER']);
        $user->setClients($clientRepository->findOneBy(['id' => 4]));
        $user->setCreatedAt(new DateTimeImmutable());
        // Vérification si l'email existe déjà
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser) {
            return $this->json(['message' => 'Un utilisateur avec ce courriel existe déjà.'], 400);
        }

        // Validation des données de l'utilisateur
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['message' => (string)$errors], 400);
        }

        // Hachage du mot de passe
        $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()));
        // Persistence et enregistrement de l'utilisateur
        $em->persist($user);
        $em->flush();
        return $this->json(['message' => 'Registered Successfully'], 201);
    }

    /**
     * @Route("/", name="user_index", methods={"GET"})
     */
    public function index(PaginatorInterface $paginator, ManagerRegistry $doctrine, SerializerInterface $serializer, Request $request): JsonResponse
    {
        $queryBuilder = $doctrine->getRepository(User::class)->createQueryBuilder('u');

        // On ajoute des filtres si nécessaire
        $name = $request->query->get('name');
        if ($name) {
            $queryBuilder
                ->where('u.name LIKE :name')
                ->setParameter('name', '%' . $name . '%');
        }

        $pagination = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            13
        );

        $data = [
            'items' => $pagination->getItems(),
            'total_items' => $pagination->getTotalItemCount(),
            'page' => $pagination->getCurrentPageNumber(),
            'limit' => $pagination->getItemNumberPerPage(),
        ];

        $json = $serializer->serialize($data, 'json', ['groups' => ["getUsers", "getClients"]]);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/{id}', name: 'detailUser', methods: ['GET'])]
    public function getDetailUser($id, SerializerInterface $serializer, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);
        if ($user) {
            $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUsers']);
            return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    /**
     * @Route("/{id}", name="user_delete", methods={"DELETE"})
     */
    public function delete(ManagerRegistry $doctrine, int $id): JsonResponse
    {
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json('No user found for id' . $id, 404);
        }
        $entityManager->remove($user);
        $entityManager->flush();
        return new JsonResponse(["message" => "Suppression effectuée avec succès."], Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{id}", name="user_edit", methods={"PUT"})
     */
    public function edit(EntityManagerInterface $em, Request $request, int $id, SerializerInterface $serializer, ValidatorInterface $validator, User $currentUser): JsonResponse
    {
        $updatedUser = $serializer->deserialize($request->getContent(), User::class, "json", [AbstractNormalizer::OBJECT_TO_POPULATE => $currentUser]);
        $errors = $validator->validate($updatedUser);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
        }
        $em->persist($updatedUser);
        $em->flush();
        return $this->json(["message" => "Mise à jour effectuée avec succès."], Response::HTTP_OK);
    }
}
