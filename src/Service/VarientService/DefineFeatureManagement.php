<?php

namespace App\Service\VarientService;

use App\Entity\ProductItem\DefineFeature;
use App\Repository\ProductItem\DefineFeatureRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ProductItem\ItemFeatureRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class DefineFeatureManagement
{
    private $em;
    private $defineFeatureRepository;
    private $itemFeatureRepository;
    private $serializer;

    public function __construct(EntityManagerInterface $em , DefineFeatureRepository $defineFeatureRepository , ItemFeatureRepository $itemFeatureRepository)
    {
        $this->em = $em;
        $this->defineFeatureRepository = $defineFeatureRepository;
        $this->itemFeatureRepository = $itemFeatureRepository;
        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }
    
    public function defineFeature($features){
        foreach($features as $feature => $value){
            $itemfeature = $this->itemFeatureRepository->readFeatureById($feature);
            if(!$itemfeature){
                throw new \Exception("Invalid Feature ID");
            }
            $definefeature = new DefineFeature();
            $definefeature->setValue($value);
            $definefeature->setStatus(true);
            $definefeature->setItemFeature($itemfeature);
            $this->defineFeatureRepository->add($definefeature,true);

            $itemfeature->addDefineFeature($definefeature);
            $this->em->persist($itemfeature);
            $this->em->flush();
        }
        return true;
    }

    public function readFeatureDefinedById($id): DefineFeature{
        if(!$this->defineFeatureRepository->find($id)){
            throw new \Exception("Feature value not found");
        }
        return $this->defineFeatureRepository->find($id);
    }

    public function updateFeatureDefined($id, $value){
        $definefeature = $this->readFeatureDefinedById($id);
        $definefeature->setValue($value[$id]);
        return $this->defineFeatureRepository->add($definefeature,true);
    }

    public function showFeaturesDefined(){
        return $this->defineFeatureRepository->showFeature(['status' => 1]);
    }

    public function deleteFeatureDefined($id){
        return $this->readFeatureDefinedById($id)->setStatus(false);
    }
}