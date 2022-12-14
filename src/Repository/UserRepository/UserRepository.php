<?php

namespace App\Repository\UserRepository;

use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function add(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQuery(
            'SELECT u
                FROM App\Entity\User\User u
                WHERE u.phone_number = :query'
        )
            ->setParameter('query', $identifier)
            ->getOneOrNullResult();
    }

    public function findBySellerIds($purchaseId): ?array
    {
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $query = $connection->prepare
        (
            "SELECT DISTINCT seller_id FROM variant 
                WHERE id IN 
                      (SELECT variant_id 
                      FROM purchase_item 
                      WHERE purchase_id=:id)"
        );
        $query->bindValue('id',$purchaseId,ParameterType::INTEGER);
        return $query->executeQuery()->fetchAllAssociative();
    }
}
