<?php

namespace App\Controller;

use ReflectionClass;
use App\Entity\Product;
use App\Repository\ProductRepository;
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
 * @Route("/api/product", name="api_")
 */
class ProductController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/", name="product_index", methods={"GET"})
     */
    public function index(
        TagAwareCacheInterface $cachePool,
        PaginatorInterface $paginator,
        SerializerInterface $serializer,
        Request $request
    ): JsonResponse {
        $name = $request->query->get('name');
        $orderBy = $request->query->get('orderBy', 'name');
        $direction = $request->query->get('direction', 'asc');

        $cacheKey = 'products_index_' . md5(json_encode([$name, $orderBy, $direction]));

        if ($cachePool->hasItem($cacheKey)) {
            $json = $cachePool->getItem($cacheKey)->get();
            return new JsonResponse($json, 200, [], true);
        }

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->orderBy('p.' . $orderBy, $direction);

        if ($name) {
            $queryBuilder
                ->andWhere('p.name LIKE :name')
                ->setParameter('name', '%' . $name . '%');
        }

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            5 // nombre d'éléments par page
        );

        $products = $pagination->getItems();

        $data = [
            'items' => $products,
            'total_items' => $pagination->getTotalItemCount(),
            'page' => $pagination->getCurrentPageNumber(),
            'limit' => $pagination->getItemNumberPerPage(),
        ];

        $json = $serializer->serialize($data, 'json', ['groups' => 'getProducts']);
        $response = new JsonResponse($json, 200, [], true);

        $cacheItem = $cachePool->getItem($cacheKey);
        $cacheItem->set($json);
        $cacheItem->tag(['products']);
        $cachePool->save($cacheItem);

        return $response;
    }


    #[Route('/{id}', name: 'detailProduct', methods: ['GET'])]
    public function getDetailProduct($id, SerializerInterface $serializer, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->findOneById($id);
        if ($product) {
            $jsonProduct = $serializer->serialize($product, 'json', ['groups' => 'getProducts']);
            return new JsonResponse($jsonProduct, 200, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    /**
     * @Route("", name="product_new", methods={"POST"})
     */
    public function new(SerializerInterface $serializer, ManagerRegistry $doctrine, ValidatorInterface $validator, Request $request): JsonResponse
    {
        $entityManager = $doctrine->getManager();
        $product = $serializer->deserialize($request->getContent(), Product::class, 'json');
        $errors = $validator->validate($product);
        if (count($errors) > 0) {
            return $this->json(['message' => (string)$errors], 400);
        }
        $entityManager->persist($product);
        $entityManager->flush();
        return $this->json(['message' => 'Nouveau produit créé avec succès avec l\'id ' . $product->getId()], 201);
    }

    /**
     * @Route("/{id}", name="product_edit", methods={"PUT"})
     */
    public function edit(EntityManagerInterface $em, Request $request, int $id, SerializerInterface $serializer, ValidatorInterface $validator, Product $currentProduct): JsonResponse
    {
        $updatedProduct = $serializer->deserialize($request->getContent(), Product::class, "json", [AbstractNormalizer::OBJECT_TO_POPULATE => $currentProduct]);
        $errors = $validator->validate($updatedProduct);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['message' => implode(', ', $errorMessages)], 400);
        }
        $em->persist($updatedProduct);
        $em->flush();
        return $this->json(["message" => "Produit mis à jour avec succès."], 200);
    }

    /**
     * @Route("/{id}", name="product_delete", methods={"DELETE"})
     */
    public function delete(ManagerRegistry $doctrine, int $id, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // Supprime l'entrée de cache associée au produit
        $cacheKey = 'products_index_cache_key';
        $cachePool->deleteItem($cacheKey);
        $entityManager = $doctrine->getManager();
        $product = $entityManager->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json('No product found for id' . $id, 404);
        }
        $entityManager->remove($product);
        $entityManager->flush();
        return $this->json('Deleted a product successfully with id ' . $id, 204);
    }
}
