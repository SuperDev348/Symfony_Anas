<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return Order[]
     */
    public function findWithFilter($filter): array
    {
        $query = $this->createQueryBuilder('order');
        foreach ($filter as $key => $value)
        {
            $query->andWhere($query->expr()->andX(
                $query->expr()->like("order.".$key, ":keyword_".$key)               
            ));
            $query->setParameter("keyword_".$key, '%'.$value.'%');
        }
        return $query->orderBy('order.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Order[]
     */
    public function findMaxOrder(): array
    {
        $query = $this->createQueryBuilder('o');
        return $query->orderBy('o.order_id', 'DESC')
            ->setMaxResults( 1 )
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Order[]
     */
    public function findWithOrderId($order_id): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            "SELECT o
            FROM App\Entity\Order o
            WHERE o.order_id = '" . $order_id . "'
            ORDER BY o.id ASC"
        );

        // returns an array of Product objects
        return $query->getResult();
    }

    // /**
    //  * @return Order[] Returns an array of Order objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Order
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
