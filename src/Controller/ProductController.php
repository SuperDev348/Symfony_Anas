<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use App\Entity\Product;

class ProductController extends AbstractController
{
  protected $session;
  public function __construct(SessionInterface $session)
  {
    $this->session = $session;
  }

  private function isAuth() {
    if(is_null($this->session->get('user'))){
      return false;
    }
    $user = $this->getDoctrine()->getRepository(User::class)->find($this->session->get('user')->getId());
    if ($user->getBan()) {
      $this->session->clear();
      return false;
    }
    return true;
  }

  private function isAdmin() {
    if(is_null($this->session->get('user'))||$this->session->get('user')->getType()!="admin"){
      return false;
    }
    $user = $this->getDoctrine()->getRepository(User::class)->find($this->session->get('user')->getId());
    if ($user->getBan()) {
      $this->session->clear();
      return false;
    }
    return true;
  }
  /**
   * @Route("/product", name="product")
   */
  public function index(): Response
  {
    $products = $this->getDoctrine()->getRepository(Product::class)->findAll();
    return $this->render('pages/product/index.html.twig', [
      'products' => $products,
    ]);
  }

  /**
   * @Route("/product/detail/{id}", name="product_detail")
   */
  public function detail($id): Response
  {
    $product = $this->getDoctrine()->getRepository(Product::class)->find($id);
    $sizes = $this->sizes();
    return $this->render('pages/product/detail.html.twig', [
      'product' => $product,
      'sizes' => $sizes
    ]);
  }

  /**
   * @Route("/admin/product", name="admin_product")
   */
  public function admin_index(): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $products = $this->getDoctrine()->getRepository(Product::class)->findAll();
    return $this->render('pages/admin/product/index.html.twig', [
      'products' => $products,
    ]);
  }

  /**
   * @Route("/admin/product/create", name="admin_product_create")
   */
  public function admin_create(): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $sizes = $this->sizes();
    return $this->render('pages/admin/product/create.html.twig', [
      'sizes' => $sizes
    ]);
  }

  /**
   * @Route("/admin/product/store", name="admin_product_store")
   */
  public function admin_store(Request $request, ValidatorInterface $validator): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $name = $request->request->get("name");
    $discription = $request->request->get("discription");
    $quantity = $request->request->get("quantity");
    $size = $request->request->get("size");
    $color = $request->request->get("color");
    $type = $request->request->get("type");
    $supplier = $request->request->get("supplier");
    $price = $request->request->get("price");
    $input = [
      'name' => $name,
      'discription' => $discription,
      'quantity' => $quantity,
      'size' => $size,
      'color' => $color,
      'type' => $type,
      'supplier' => $supplier,
      'price' => $price,
    ];
    $constraints = new Assert\Collection([
      'name' => [new Assert\NotBlank],
      'discription' => [new Assert\NotBlank],
      'quantity' => [new Assert\NotBlank],
      'size' => [new Assert\NotBlank],
      'color' => [new Assert\NotBlank],
      'type' => [new Assert\NotBlank],
      'supplier' => [new Assert\NotBlank],
      'price' => [new Assert\NotBlank, new Assert\Type(['type' => 'numeric'])],
    ]);
    $violations = $validator->validate($input, $constraints);
    if (count($violations) > 0) {
      $accessor = PropertyAccess::createPropertyAccessor();
      $errorMessages = [];
      foreach ($violations as $violation) {
        $accessor->setValue($errorMessages,
        $violation->getPropertyPath(),
        $violation->getMessage());
      }
      $sizes = $this->sizes();
      return $this->render('pages/admin/product/create.html.twig', [
        'sizes' => $sizes,
        'errors' => $errorMessages,
        'old' => $input
      ]);
    }
    
    $product = new Product();
    $name = $request->request->get('name');
    $product->setName($name);
    $discription = $request->request->get('discription');
    $product->setDiscription($discription);
    $quantity = $request->request->get('quantity');
    $product->setQuantity($quantity);
    $size = $request->request->get('size');
    $product->setSize($size);
    $color = $request->request->get('color');
    $product->setColor($color);
    $type = $request->request->get('type');
    $product->setType($type);
    $supplier = $request->request->get('supplier');
    $product->setSupplier($supplier);
    $price = $request->request->get('price');
    $product->setPrice($price);
    
    $image_file = $request->files->get('image');
    if ($image_file) {
      $originalFilename = pathinfo($image_file->getClientOriginalName(), PATHINFO_FILENAME);
      // this is needed to safely include the file name as part of the URL
      $safeFilename = $this->generateRandomString();
      $newFilename = $safeFilename.'.'.$image_file->guessExtension();

      // Move the file to the directory where brochures are stored
      try {
        $image_file->move(
          'upload/products/',
          $newFilename
        );
      } catch (FileException $e) {
        // ... handle exception if something happens during file upload
      }

      // updates the 'brochureFilename' property to store the PDF file name
      // instead of its contents
      $product->setImage('upload/products/'.$newFilename);
    }
    else {
      $errorMessages = ['image' => 'this field is require'];
      $sizes = $this->sizes();
      return $this->render('pages/admin/product/create.html.twig', [
        'sizes' => $sizes,
        'errors' => $errorMessages,
        'old' => $input
      ]);
    }
    
    // save
    $doct = $this->getDoctrine()->getManager();
    $doct->persist($product);
    $doct->flush();
    return $this->redirectToRoute('admin_product');
  }

  /**
   * @Route("/admin/product/edit/{id}", name="admin_product_edit")
   */
  public function admin_edit($id): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $product = $this->getDoctrine()->getRepository(Product::class)->find($id);
    $sizes = $this->sizes();
    return $this->render('pages/admin/product/edit.html.twig', [
      'product' => $product,
      'sizes' => $sizes,
    ]);
  }

  /**
   * @Route("/admin/product/update/{id}", name="admin_product_update")
   */
  public function admin_update($id, Request $request, ValidatorInterface $validator): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $name = $request->request->get("name");
    $discription = $request->request->get("discription");
    $quantity = $request->request->get("quantity");
    $size = $request->request->get("size");
    $color = $request->request->get("color");
    $type = $request->request->get("type");
    $supplier = $request->request->get("supplier");
    $price = $request->request->get("price");
    $input = [
      'name' => $name,
      'discription' => $discription,
      'quantity' => $quantity,
      'size' => $size,
      'color' => $color,
      'type' => $type,
      'supplier' => $supplier,
      'price' => $price,
    ];
    $constraints = new Assert\Collection([
      'name' => [new Assert\NotBlank],
      'discription' => [new Assert\NotBlank],
      'quantity' => [new Assert\NotBlank],
      'size' => [new Assert\NotBlank],
      'color' => [new Assert\NotBlank],
      'type' => [new Assert\NotBlank],
      'supplier' => [new Assert\NotBlank],
      'price' => [new Assert\NotBlank],
    ]);
    $violations = $validator->validate($input, $constraints);
    if (count($violations) > 0) {
      $accessor = PropertyAccess::createPropertyAccessor();
      $errorMessages = [];
      foreach ($violations as $violation) {
        $accessor->setValue($errorMessages,
        $violation->getPropertyPath(),
        $violation->getMessage());
      }
      $sizes = $this->sizes();
      $product = $this->getDoctrine()->getRepository(Product::class)->find($id);
      return $this->render('pages/admin/product/edit.html.twig', [
        'sizes' => $sizes,
        'product' => $product,
        'errors' => $errorMessages,
        'old' => $input
      ]);
    }
    
    $doct = $this->getDoctrine()->getManager();
    $product = $doct->getRepository(Product::class)->find($id);
    $name = $request->request->get('name');
    $product->setName($name);
    $discription = $request->request->get('discription');
    $product->setDiscription($discription);
    $quantity = $request->request->get('quantity');
    $product->setQuantity($quantity);
    $size = $request->request->get('size');
    $product->setSize($size);
    $color = $request->request->get('color');
    $product->setColor($color);
    $type = $request->request->get('type');
    $product->setType($type);
    $supplier = $request->request->get('supplier');
    $product->setSupplier($supplier);
    $price = $request->request->get('price');
    $product->setPrice($price);
    
    $image_file = $request->files->get('image');
    if ($image_file) {
      $originalFilename = pathinfo($image_file->getClientOriginalName(), PATHINFO_FILENAME);
      // this is needed to safely include the file name as part of the URL
      $safeFilename = $this->generateRandomString();
      $newFilename = $safeFilename.'.'.$image_file->guessExtension();

      // Move the file to the directory where brochures are stored
      try {    
        $image_file->move(
          'upload/products/',
          $newFilename
        );
      } catch (FileException $e) {
        // ... handle exception if something happens during file upload
      }

      // updates the 'brochureFilename' property to store the PDF file name
      // instead of its contents
      $product->setImage('upload/products/'.$newFilename);
    }
    else {
      $errorMessages = ['image' => 'this field is require'];
      $sizes = $this->sizes();
      $product = $this->getDoctrine()->getRepository(Product::class)->find($id);
      return $this->render('pages/admin/product/edit.html.twig', [
        'product' => $product,
        'sizes' => $sizes,
        'errors' => $errorMessages,
        'old' => $input
      ]);
    }
    
    // update
    $doct->flush();
    return $this->redirectToRoute('admin_product', [
      'id' => $product->getId()
    ]);
  }

  /**
   * @Route("/admin/product/delete/{id}", name="admin_product_delete")
   */
  public function admin_delete($id): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $doct = $this->getDoctrine()->getManager();
    $product = $doct->getRepository(Product::class)->find($id);
    $doct->remove($product);
    $doct->flush();
    return $this->redirectToRoute('admin_product', [
        'id' => $product->getId()
    ]);
  }

  // /**
  //  * @Route("/admin/blog/search", name="admin_blog_search")
  //  */
  // public function admin_search(Request $request): Response
  // {
  //     if (!$this->isAdmin())
  //         return $this->redirectToRoute('deconnexion');
  //     $type_id = $request->request->get('type_id');
  //     $filter = [];
  //     if ($type_id != '0')
  //         $filter['type_id'] = $type_id;
      
  //     $doct = $this->getDoctrine()->getManager();
  //     $blogs = $doct->getRepository(Blog::class)->findWithFilter($filter);
  //     foreach($blogs as $blog) {
  //         $blogtype = $this->getDoctrine()->getRepository(Blogtype::class)->find($blog->getTypeId());
  //         $blog->type = $blogtype->getName();
  //         $blog->user = $this->getDoctrine()->getRepository(User::class)->find($blog->getUserId());
  //     }
  //     $types = $doct->getRepository(Blogtype::class)->findAll();
  //     return $this->render('pages/admin/blog/index.html.twig', [
  //         'blogs' => $blogs,
  //         'filter' => $filter,
  //         'types' => $types
  //     ]);
  // }

  private function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  private function sizes() {
    return ['l', 'm', 's'];
  }
}
