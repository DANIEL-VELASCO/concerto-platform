<?php

namespace Concerto\PanelBundle\Repository;

use Concerto\PanelBundle\Repository\AEntityRepository;

/**
 * ViewTemplateRepository
 */
class ViewTemplateRepository extends AEntityRepository
{
    public function findOneByName($name)
    {
        return $this->getEntityManager()->getRepository("ConcertoPanelBundle:ViewTemplate")->findOneBy(array("name" => $name));
    }

    public function findDirectlyLocked()
    {
        $qb = $this->getEntityManager()->createQueryBuilder()->select("vt")->from("Concerto\PanelBundle\Entity\ViewTemplate", "vt")->where("vt.directLockBy IS NOT NULL");
        return $qb->getQuery()->getResult();
    }
}
