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
   * @Route("/cart/update/{id}", name="cart_update")
   */
  public function cart_update($id, Request $request): Response
  {
    $doct = $this->getDoctrine()->getManager();
    $cart = $doct->getRepository(Cart::class)->find($id);
    $quantity = $request->request->get('quantity');
    $cart->setQuantity($quantity);
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
  public function checkout(Request $request): Response
  {
    $doct = $this->getDoctrine()->getManager();
    $carts = $doct->getRepository(Cart::class)->findAll();
    $total_price = 0;
    foreach ($carts as $cart) {
      $quantity = $request->request->get($cart->getId());
      $cart->setQuantity($quantity);
      $cart->product = $doct->getRepository(Product::class)->find($cart->getProductId());
      $total_price = $total_price + $cart->product->getPrice() * $cart->getQuantity();
    }
    $doct->flush();
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
    $first_name = $request->request->get("first_name");
    $last_name = $request->request->get("last_name");
    $email = $request->request->get("email");
    $company = $request->request->get("company");
    $shipping_address = $request->request->get("shipping_address");
    $country = $request->request->get("country");
    $zip_code = $request->request->get("zip_code");
    $input = [
      'first_name' => $first_name,
      'last_name' => $last_name,
      'email' => $email,
      'company' => $company,
      'shipping_address' => $shipping_address,
      'country' => $country,
      'zip_code' => $zip_code,
    ];
    $constraints = new Assert\Collection([
      'first_name' => [new Assert\NotBlank],
      'last_name' => [new Assert\NotBlank],
      'email' => [new Assert\NotBlank],
      'company' => [new Assert\NotBlank],
      'shipping_address' => [new Assert\NotBlank],
      'country' => [new Assert\NotBlank],
      'zip_code' => [new Assert\NotBlank],
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
      $carts = $this->getDoctrine()->getRepository(Cart::class)->findAll();
      $total_price = 0;
      foreach ($carts as $cart) {
        $cart->product = $this->getDoctrine()->getRepository(Product::class)->find($cart->getProductId());
        $total_price = $total_price + $cart->product->getPrice() * $cart->getQuantity();
      }
      $shipping_price = number_format($total_price * 19 /100, 2, '.', ' ');
      $total_price = number_format($total_price + $shipping_price, 2, '.', ' ');
      return $this->render('pages/order/checkout.html.twig', [
        'carts' => $carts,
        'shipping_price' => $shipping_price,
        'total_price' => $total_price,
        'errors' => $errorMessages,
        'old' => $input
      ]);
    }
    
    $doct = $this->getDoctrine()->getManager();
    $carts = $this->getDoctrine()->getRepository(Cart::class)->findAll();
    $max_order_id = $this->max_order_id();
    foreach ($carts as $cart) {
      // save order
      $order = new Order();
      $first_name = $request->request->get('first_name');
      $order->setFirstName($first_name);
      $last_name = $request->request->get('last_name');
      $order->setLastName($last_name);
      $email = $request->request->get('email');
      $order->setEmail($email);
      $company = $request->request->get('company');
      $order->setCompany($company);
      $shipping_address = $request->request->get('shipping_address');
      $order->setShippingAddress($shipping_address);
      $country = $request->request->get('country');
      $order->setCountry($country);
      $zip_code = $request->request->get('zip_code');
      $order->setZipCode($zip_code);
      $comment = $request->request->get('comment');
      $order->setComment($comment);
      $product = $this->getDoctrine()->getRepository(Product::class)->find($cart->getProductId());
      $order->setProductId($cart->getProductId());
      $order->setQuantity($cart->getQuantity());
      $order->setPrice($product->getPrice() * $cart->getQuantity());
      $date = new DateTime();
      $date->add(new DateInterval('P1D'));
      $order->setDate($date);
      $order->setUserId(1);
      $order->setOrderId($max_order_id + 1);
      // update product quantity
      if ($product->getQuantity() < $cart->getQuantity()) {
        $this->clean_cart();
        return $this->render('pages/order/checkout_fail.html.twig', []);
      }
      $product->setQuantity($product->getQuantity() - $cart->getQuantity());
      $doct->persist($order);
      // remove cart
      $doct->remove($cart);
    }
    $doct->flush();
    return $this->render('pages/order/checkout_success.html.twig', [
    ]);
  }

  private function max_order_id() {
    $doct = $this->getDoctrine()->getManager();
    if (count($doct->getRepository(Order::class)->findAll()) === 0)
      return 0;
    else {
      $max_order = $this->getDoctrine()->getRepository(Order::class)->findMaxOrder();
      return $max_order[0]->getOrderId();
    }
  }

  private function clean_cart() {
    $doct = $this->getDoctrine()->getManager();
    $carts = $doct->getRepository(Cart::class)->findAll();
    foreach ($carts as $cart) {
      $doct->remove($cart);
    }
    $doct->flush();
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

  /**
   * @Route("/admin/order/search", name="admin_order_search")
   */
  public function admin_search(Request $request): Response
  {
    $doct = $this->getDoctrine()->getManager();
    $id = $request->request->get('id');
    $filter = [];
    if ($id != '0' && $id != '') {
      $filter['id'] = $id;
      $orders = $doct->getRepository(Order::class)->findWithOrderId($id);
    }
    else {
      $orders = $doct->getRepository(Order::class)->findAll();
    }
    foreach ($orders as $order) {
      $order->product = $this->getDoctrine()->getRepository(Product::class)->find($order->getProductId());
      $order->str_date = $order->getDate()->format('Y-m-d');
    }
    return $this->render('pages/admin/order/index.html.twig', [
      'orders' => $orders,
      'filter' => $filter
    ]);
  }

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
