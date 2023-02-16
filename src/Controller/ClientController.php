<?php

namespace App\Controller;

use ReflectionClass;
use App\Entity\Client;
use App\Entity\Clients;
use App\Repository\ClientRepository;
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

/**
 * @Route("/api/client", name="api_")
 */
class ClientController extends AbstractController
{
    /**
     * @Route("/", name="client_index", methods={"GET"})
     */
    public function index(TagAwareCacheInterface $cachePool, PaginatorInterface $paginator, ManagerRegistry $doctrine, SerializerInterface $serializer, Request $request): JsonResponse
    {
        // Définit le nom de la clé de cache
        $cacheKey = 'clients_index_cache_key';

        // Vérifie si la réponse est déjà en cache
        if ($cachePool->hasItem($cacheKey)) {
            $json = $cachePool->getItem($cacheKey)->get();
            return new JsonResponse($json, 200, [], true);
        }

        $clients = $doctrine
            ->getRepository(Clients::class)
            ->findAll();
        $pagination = $paginator->paginate(
            $clients,
            $request->query->getInt('page', 1),
            5
        );
        $data = [
            'items' => $pagination->getItems(),
            'total_items' => $pagination->getTotalItemCount(),
            'page' => $pagination->getCurrentPageNumber(),
            'limit' => $pagination->getItemNumberPerPage(),
        ];
        $json = $serializer->serialize($data, 'json');

        // Stocke la réponse en cache
        $cacheItem = $cachePool->getItem($cacheKey);
        $cacheItem->set($json);
        $cacheItem->tag(['clients']);
        $cachePool->save($cacheItem);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/{id}', name: 'detailClient', methods: ['GET'])]
    public function getDetailClient(int $id, SerializerInterface $serializer, ClientsRepository $clientRepository): JsonResponse
    {
        $client = $clientRepository->find($id);
        if ($client) {
            $jsonClient = $serializer->serialize($client, 'json');
            return new JsonResponse($jsonClient, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    /**
     * @Route("", name="client_new", methods={"POST"})
     */
    public function new(SerializerInterface $serializer, ManagerRegistry $doctrine, ValidatorInterface $validator, Request $request): JsonResponse
    {
        $entityManager = $doctrine->getManager();
        $client = $serializer->deserialize($request->getContent(), Clients::class, 'json');
        $errors = $validator->validate($client);
        if (count($errors) > 0) {
            return $this->json(['message' => (string)$errors], 400);
        }
        $entityManager->persist($client);
        $entityManager->flush();
        return $this->json(['message' => 'Nouveau produit créé avec succès avec l\'id ' . $client->getId()], 201);
    }

    /**
     * @Route("/{id}", name="client_edit", methods={"PUT"})
     */
    public function edit(EntityManagerInterface $em, Request $request, int $id, SerializerInterface $serializer, ValidatorInterface $validator, Clients $currentClient): JsonResponse
    {
        $updatedClient = $serializer->deserialize($request->getContent(), Clients::class, "json", [AbstractNormalizer::OBJECT_TO_POPULATE => $currentClient]);
        $errors = $validator->validate($updatedClient);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['message' => implode(', ', $errorMessages)], 400);
        }
        $em->persist($updatedClient);
        $em->flush();
        return $this->json(["message" => "Produit mis à jour avec succès."]);
    }

    /**
     * @Route("/{id}", name="client_delete", methods={"DELETE"})
     */
    public function delete(ManagerRegistry $doctrine, int $id, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // Supprime l'entrée de cache associée au produit
        $cacheKey = 'clients_index_cache_key';
        $cachePool->deleteItem($cacheKey);
        $entityManager = $doctrine->getManager();
        $client = $entityManager->getRepository(Clients::class)->find($id);
        if (!$client) {
            return $this->json('No client found for id' . $id, 404);
        }
        $entityManager->remove($client);
        $entityManager->flush();
        return $this->json('Deleted a client successfully with id ' . $id);
    }
}
