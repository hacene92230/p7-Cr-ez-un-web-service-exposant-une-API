<?php

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use App\Entity\Clients;
use App\Repository\ClientsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\PasswordHasher\Hasher\ClientsPasswordHasherInterface;

/**
 * @Route("/api/client", name="api_client")
 */
class ClientController extends AbstractController
{
    /**
     * @Route("", name="client_new", methods={"POST"})
     */
    public function new(SerializerInterface $serializer, ManagerRegistry $doctrine, Request $request): JsonResponse
    {
        $entityManager = $doctrine->getManager();
        $client = $serializer->deserialize($request->getContent(), Clients::class, 'json');
        $entityManager->persist($client);
        $entityManager->flush();
        return $this->json(['message' => 'Nouveau client créé avec succès avec l\'id ' . $client->getId()], 201);
    }

    /**
     * @Route("/", name="client_index", methods={"GET"})
     */
    public function index(PaginatorInterface $paginator, ManagerRegistry $doctrine, SerializerInterface $serializer, Request $request): JsonResponse
    {
        $clients = $doctrine
            ->getRepository(Clients::class)
            ->findAll();
        $pagination = $paginator->paginate(
            $clients,
            $request->query->getInt('page', 1),
            12
        );
        $data = [
            'items' => $pagination->getItems(),
            'total_items' => $pagination->getTotalItemCount(),
            'page' => $pagination->getCurrentPageNumber(),
            'limit' => $pagination->getItemNumberPerPage(),
        ];
        $json = $serializer->serialize($data, 'json', ['groups' => "getClients"]);
        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/{id}', name: 'detailClients', methods: ['GET'])]
    public function getDetailClients($id, SerializerInterface $serializer, ClientsRepository $clientRepository): JsonResponse
    {
        $client = $clientRepository->find($id);
        if ($client) {
            $jsonClients = $serializer->serialize($client, 'json', ['groups' => 'getClients']);
            return new JsonResponse($jsonClients, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    /**
     * @Route("/{id}", name="client_edit", methods={"PUT"})
     */
    public function edit(EntityManagerInterface $em, Request $request, int $id, SerializerInterface $serializer, ValidatorInterface $validator, Clients $currentClients): JsonResponse
    {
        $updatedClients = $serializer->deserialize($request->getContent(), Clients::class, "json", [AbstractNormalizer::OBJECT_TO_POPULATE => $currentClients]);
        $errors = $validator->validate($updatedClients);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
        }
        $em->persist($updatedClients);
        $em->flush();
        return $this->json(["message" => "Mise à jour effectuée avec succès."], Response::HTTP_OK);
    }

    /**
     * @Route("/{id}", name="client_delete", methods={"DELETE"})
     */
    public function delete(ManagerRegistry $doctrine, int $id, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // Supprime l'entrée de cache associée au client
        $cacheKey = 'clients_index_cache_key';
        $cachePool->deleteItem($cacheKey);
        $entityManager = $doctrine->getManager();
        $client = $entityManager->getRepository(Clients::class)->find($id);
        if (!$client) {
            return $this->json('No client found for id' . $id, 404);
        }
        $users = $entityManager->getRepository(User::class)->findBy(['clients' => $client]);
        foreach ($users as $user) {
            $entityManager->remove($user);
        }
        $entityManager->remove($client);
        $entityManager->flush();
        return new JsonResponse(["message" => "Suppression effectuée avec succès."], Response::HTTP_NO_CONTENT);
    }
}
