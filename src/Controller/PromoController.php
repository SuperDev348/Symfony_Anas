<?php

namespace App\Controller;

use App\Entity\Promotion;
use App\Form\PromotionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PromoController extends AbstractController
{
    /**
     * @Route("/admin/promos", name="admin_promos")
     */
    public function promoList(Request $request, EntityManagerInterface $manager): Response
    {
        $promos = $this->getDoctrine()->getRepository(Promotion::class)->findAll();

        $promo = new Promotion();
        $form = $this->createForm(PromotionType::class, $promo);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $manager->persist($promo);
            $manager->flush();
            return $this->redirectToRoute('admin_promos');
        }
        return $this->render('pages/admin/promo/liste_promo.html.twig', ["form"=>$form->createView(), "promos"=>$promos]);
    }

    /**
     * @Route("admin/update-promo/{id}", name="update_promo")
     */
    public function update(Request $request, EntityManagerInterface $manager, $id): Response
    {
        if ($request->isMethod('post')) {
            // your code
            $promo = $this->getDoctrine()->getRepository(Promotion::class)->find($id);
            $promo->setTitre($request->request->get('titre'));
            $promo->setTypeProduit($request->request->get('type_produit'));
            $promo->setPourcentage($request->request->get('pourcentage'));
            $promo->setDescription($request->request->get('description'));
            $promo->setDateDebut(\DateTime::createFromFormat('Y-m-d', $request->request->get('DateDebut')));
            $promo->setDateFin(\DateTime::createFromFormat('Y-m-d', $request->request->get('DateFin')));


            $manager->flush();
            return $this->redirectToRoute('admin_promos');
        }

    }

    /**
     * @Route("/admin/delete-promo/{id}", name="delete_promo")
     */
    public function delete($id, EntityManagerInterface $manager): Response
    {
        $promo = $this->getDoctrine()->getRepository(Promotion::class)->find($id);
        $manager->remove($promo);
        $manager->flush();
        return $this->redirectToRoute('admin_promos');
    }
    /**
     * @return string
     */
    private function generateUniqueFileName()
    {
        return md5(uniqid());
    }


}

