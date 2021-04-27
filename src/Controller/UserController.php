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
use App\Entity\User;

class UserController extends AbstractController
{
  protected $session;
  public function __construct(SessionInterface $session)
  {
    $this->session = $session;
  }

  /**
   * @Route("/login", name="login")
   */
  public function index(): Response
  {
    return $this->render('pages/user/login.html.twig', [
    ]);
  }

  /**
   * @Route("/signin", name="signin")
   */
  public function signin(Request $request, ValidatorInterface $validator): Response
  {
    $email = $request->request->get("email");
    $password = $request->request->get("password");
    $input = [
      'email' => $email,
      'password' => $password,
    ];
    $constraints = new Assert\Collection([
      'email' => [new Assert\NotBlank],
      'password' => [new Assert\NotBlank],
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
      return $this->render('pages/user/login.html.twig', [
        'errors' => $errorMessages,
        'old' => $input
      ]);
    }
    
    $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
      'email' => $email,
    ]);
    if (is_null($user)||password_verify($password, $user->getPassword())==false) {
      return $this->render('pages/user/login.html.twig',['message'=>"Email or password not valid" ]);
    }
    else {
      $this->session->set('user', $user);
      if ($user->getRole() == "client") {
        return $this->redirectToRoute('product');
      } elseif ($user->getRole() == "admin") {
        return $this->redirectToRoute('admin_product');
      }
    }
    return $this->render('pages/user/login.html.twig');
  }

  /**
   * @Route("/signup", name="signup")
   */
  public function signup(Request $request, ValidatorInterface $validator): Response
  {
    $email = $request->request->get("email");
    $password = $request->request->get("password");
    $confirm_password = $request->request->get("confirm_password");
    $input = [
      'email' => $email,
      'password' => $password,
      'confirm_password' => $confirm_password,
    ];
    $constraints = new Assert\Collection([
      'email' => [new Assert\NotBlank],
      'password' => [new Assert\NotBlank],
      'confirm_password' => [new Assert\NotBlank],
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
      if ($password !== '' && $confirm_password !== '' && $password !== $confirm_password) {
        $errorMessages->confirm_password = 'confirm password must be equal.';
      }
      return $this->render('pages/user/login.html.twig', [
        'errors' => $errorMessages,
        'old' => $input
      ]);
    }
    
    $user = new User();
    $email = $request->request->get('email');
    $user->setEmail($email);
    $password = $request->request->get('password');
    $user->setPassword(password_hash($password, PASSWORD_DEFAULT));
    $olduser=$this->getDoctrine()->getRepository(User::class)->findOneBy(['email' => $email]);
    if(is_null($olduser)) {
      $user->setRole('client');
      $doct = $this->getDoctrine()->getManager();
      $doct->persist($user);
      $doct->flush();
      $this->session->clear();
      $this->session->set('user', $user);
      return $this->redirectToRoute('product');
    }
    $errorMessages = ['message' => 'This email already exist'];
    return $this->render('pages/user/login.html.twig', [
      'errors' => $errorMessages,
    ]);
  }

  /**
   * @Route("/logout", name="logout")
   */
  public function logout(): Response
  {
    $this->session->clear();
    return $this->redirectToRoute('login');
  }
}
