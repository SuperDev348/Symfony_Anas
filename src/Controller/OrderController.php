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
use App\Entity\Cart;
use App\Entity\Order;
use \Datetime;
use \DateInterval;

class OrderController extends AbstractController
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
   * @Route("/cart/add/{id}", name="cart_add")
   */
  public function cart_add($id): Response
  {
    $doct = $this->getDoctrine()->getManager();
    $carts = $doct->getRepository(Cart::class)->findAll();
    $is_create = true;
    foreach ($carts as $cart) {
      if ($cart->getProductId() == $id) {
        $is_create = false;
        $cart->setQuantity($cart->getQuantity() + 1);
      }
    }
    if ($is_create) {
      $cart = new Cart();
      $cart->setProductId($id);
      $cart->setUserId(1);
      $cart->setQuantity(1);
      $doct->persist($cart);
    }
    $doct->flush();
    return $this->redirectToRoute('product');
  }

  /**
   * @Route("/cart", name="cart")
   */
  public function cart(): Response
  {
    $carts = $this->getDoctrine()->getRepository(Cart::class)->findAll();
    foreach ($carts as $cart) {
      $cart->product = $this->getDoctrine()->getRepository(Product::class)->find($cart->getProductId());
    }
    return $this->render('pages/order/cart.html.twig', [
      'carts' => $carts,
    ]);
  }

  /**
   * @Route("/checkout", name="checkout")
   */
  public function checkout(): Response
  {
    $carts = $this->getDoctrine()->getRepository(Cart::class)->findAll();
    $total_price = 0;
    foreach ($carts as $cart) {
      $cart->product = $this->getDoctrine()->getRepository(Product::class)->find($cart->getProductId());
      $total_price = $total_price + $cart->product->getPrice() * $cart->getQuantity();
    }
    $shipping_price = number_format($total_price * 19 /100, 2, '.', ' ');
    $total_price = number_format($total_price + $shipping_price, 2, '.', ' ');;
    return $this->render('pages/order/checkout.html.twig', [
      'carts' => $carts,
      'shipping_price' => $shipping_price,
      'total_price' => $total_price,
    ]);
  }

  /**
   * @Route("/order/store", name="order_store")
   */
  public function order_store(Request $request, ValidatorInterface $validator): Response
  {
    $shipping_address = $request->request->get("shipping_address");
    $input = [
      'shipping_address' => $shipping_address,
    ];
    $constraints = new Assert\Collection([
      'shipping_address' => [new Assert\NotBlank],
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
      return $this->redirectToRoute('checkout');
    }
    
    $doct = $this->getDoctrine()->getManager();
    $carts = $this->getDoctrine()->getRepository(Cart::class)->findAll();
    foreach ($carts as $cart) {
      $order = new Order();
      $shipping_address = $request->request->get('shipping_address');
      $order->setShippingAddress($shipping_address);
      $product = $this->getDoctrine()->getRepository(Product::class)->find($cart->getProductId());
      $order->setProductId($cart->getProductId());
      $order->setQuantity($cart->getQuantity());
      $order->setPrice($product->getPrice() * $cart->getQuantity());
      $date = new DateTime();
      $date->add(new DateInterval('P1D'));
      $order->setDate($date);
      $order->setUserId(1);
      $doct->persist($order);
      $doct->remove($cart);
    }
    $doct->flush();
    return $this->render('pages/order/checkout_success.html.twig', [
    ]);
  }

  /**
   * @Route("/admin/order", name="admin_order")
   */
  public function admin_index(): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $orders = $this->getDoctrine()->getRepository(Order::class)->findAll();
    foreach ($orders as $order) {
      $order->product = $this->getDoctrine()->getRepository(Product::class)->find($order->getProductId());
      $order->str_date = $order->getDate()->format('Y-m-d');
    }
    return $this->render('pages/admin/order/index.html.twig', [
      'orders' => $orders,
    ]);
  }

  /**
   * @Route("/admin/order/create", name="admin_order_create")
   */
  public function admin_create(): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $products = $this->getDoctrine()->getRepository(Product::class)->findAll();
    return $this->render('pages/admin/order/create.html.twig', [
      'products' => $products
    ]);
  }

  /**
   * @Route("/admin/order/store", name="admin_order_store")
   */
  public function admin_store(Request $request, ValidatorInterface $validator): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $shipping_address = $request->request->get("shipping_address");
    $quantity = $request->request->get("quantity");
    $input = [
      'shipping_address' => $shipping_address,
      'quantity' => $quantity,
    ];
    $constraints = new Assert\Collection([
      'shipping_address' => [new Assert\NotBlank],
      'quantity' => [new Assert\NotBlank],
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
      $products = $this->getDoctrine()->getRepository(Product::class)->findAll();
      return $this->render('pages/admin/order/create.html.twig', [
        'products' => $products,
        'errors' => $errorMessages,
        'old' => $input
      ]);
    }
    
    $order = new Order();
    $shipping_address = $request->request->get('shipping_address');
    $order->setShippingAddress($shipping_address);
    $quantity = $request->request->get('quantity');
    $order->setQuantity($quantity);
    $product_id = $request->request->get('product_id');
    $order->setProductId($product_id);
    $product = $this->getDoctrine()->getRepository(Product::class)->find($product_id);
    $order->setPrice($product->getPrice() * $quantity);
    $date = new DateTime();
    $date->add(new DateInterval('P1D'));
    $order->setDate($date);
    $order->setUserId(1);
    
    // save
    $doct = $this->getDoctrine()->getManager();
    $doct->persist($order);
    $doct->flush();
    return $this->redirectToRoute('admin_order');
  }

  /**
   * @Route("/admin/order/edit/{id}", name="admin_order_edit")
   */
  public function admin_edit($id): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $order = $this->getDoctrine()->getRepository(Order::class)->find($id);
    $products = $this->getDoctrine()->getRepository(Product::class)->findAll();
    return $this->render('pages/admin/order/edit.html.twig', [
      'order' => $order,
      'products' => $products,
    ]);
  }

  /**
   * @Route("/admin/order/update/{id}", name="admin_order_update")
   */
  public function admin_update($id, Request $request, ValidatorInterface $validator): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $shipping_address = $request->request->get("shipping_address");
    $quantity = $request->request->get("quantity");
    $input = [
      'shipping_address' => $shipping_address,
      'quantity' => $quantity,
    ];
    $constraints = new Assert\Collection([
      'shipping_address' => [new Assert\NotBlank],
      'quantity' => [new Assert\NotBlank],
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
      $products = $this->getDoctrine()->getRepository(Product::class)->findAll();
      $order = $this->getDoctrine()->getRepository(Order::class)->find($id);
      return $this->render('pages/admin/order/edit.html.twig', [
        'order' => $order,
        'products' => $products,
        'errors' => $errorMessages,
        'old' => $input
      ]);
    }
    
    $doct = $this->getDoctrine()->getManager();
    $order = $doct->getRepository(Order::class)->find($id);
    $shipping_address = $request->request->get('shipping_address');
    $order->setShippingAddress($shipping_address);
    $quantity = $request->request->get('quantity');
    $order->setQuantity($quantity);
    $product_id = $request->request->get('product_id');
    $order->setProductId($product_id);
    $product = $this->getDoctrine()->getRepository(Product::class)->find($product_id);
    $order->setPrice($product->getPrice() * $quantity);
    $date = new DateTime();
    $date->add(new DateInterval('P1D'));
    $order->setDate($date);
    $order->setUserId(1);
    
    // update
    $doct->flush();
    return $this->redirectToRoute('admin_order', [
      'id' => $order->getId()
    ]);
  }

  /**
   * @Route("/admin/order/delete/{id}", name="admin_order_delete")
   */
  public function admin_delete($id): Response
  {
    // if (!$this->isAdmin())
    //     return $this->redirectToRoute('deconnexion');
    $doct = $this->getDoctrine()->getManager();
    $order = $doct->getRepository(Order::class)->find($id);
    $doct->remove($order);
    $doct->flush();
    return $this->redirectToRoute('admin_order', [
        'id' => $order->getId()
    ]);
  }

  // // /**
  // //  * @Route("/admin/blog/search", name="admin_blog_search")
  // //  */
  // // public function admin_search(Request $request): Response
  // // {
  // //     if (!$this->isAdmin())
  // //         return $this->redirectToRoute('deconnexion');
  // //     $type_id = $request->request->get('type_id');
  // //     $filter = [];
  // //     if ($type_id != '0')
  // //         $filter['type_id'] = $type_id;
      
  // //     $doct = $this->getDoctrine()->getManager();
  // //     $blogs = $doct->getRepository(Blog::class)->findWithFilter($filter);
  // //     foreach($blogs as $blog) {
  // //         $blogtype = $this->getDoctrine()->getRepository(Blogtype::class)->find($blog->getTypeId());
  // //         $blog->type = $blogtype->getName();
  // //         $blog->user = $this->getDoctrine()->getRepository(User::class)->find($blog->getUserId());
  // //     }
  // //     $types = $doct->getRepository(Blogtype::class)->findAll();
  // //     return $this->render('pages/admin/blog/index.html.twig', [
  // //         'blogs' => $blogs,
  // //         'filter' => $filter,
  // //         'types' => $types
  // //     ]);
  // // }

  // private function generateRandomString($length = 10) {
  //   $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  //   $charactersLength = strlen($characters);
  //   $randomString = '';
  //   for ($i = 0; $i < $length; $i++) {
  //       $randomString .= $characters[rand(0, $charactersLength - 1)];
  //   }
  //   return $randomString;
  // }

  // private function sizes() {
  //   return ['l', 'm', 's'];
  // }
}
