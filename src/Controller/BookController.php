<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BookController extends AbstractController
{
    #[Route('/api/books', name: 'books', methods:['GET'])]
    public function getAllBooks(BookRepository $bookRepository, SerializerInterface $serializer ): JsonResponse
    {
        $bookList = $bookRepository->findAll();

        $jsonBookList = $serializer->serialize($bookList, 'json', ['groups'=>'getBooks']);

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/books/{id}', name: 'detail_book', methods:['GET'])]
    public function getDetailBook(int $id, Book $book , SerializerInterface $serializer ): JsonResponse
    {
        
        $jsonBook = $serializer->serialize($book, 'json', ['groups' =>'getBooks']);

        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
        }

     #[Route('/api/books/{id}', name: 'delete_book', methods:['DELETE'])]
    public function deleteBook(Book $book , EntityManagerInterface $em ): JsonResponse
    {
        
        $em->remove($book);
        $em->flush();

        return new JsonResponse( null,Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name:"create_book", methods:['POST'])]
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, AuthorRepository $authorRepository ,UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator ): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');
        
        $errors = $validator->validate($book);
        if($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [],true);
        }

       

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $book->setAuthor($authorRepository->find($idAuthor));

        

        $em->persist($book);
        $em->flush(); 

        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

        $location = $urlGenerator->generate('detail_book', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL );

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location" =>$location], true);
    }

    #[Route('/api/books/{id}', name:"update_book", methods:['PUT'])]
    public function updateBook(Request $request, SerializerInterface $serializer, Book $currentBook, EntityManagerInterface $em, AuthorRepository $authorRepository, ValidatorInterface $validator )
    {
        $updatedBook = $serializer->deserialize($request->getContent(), Book::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE=> $currentBook]);

        $errors = $validator->validate($updatedBook);
        if($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [],true);
        }

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $updatedBook->setAuthor($authorRepository->find($idAuthor));

        $em->persist($updatedBook);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
        
}
