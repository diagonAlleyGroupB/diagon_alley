<?php

namespace App\Service\Product;

use App\Entity\Brand\Brand;
use App\Entity\Category\Category;
use App\Entity\Product\Product;
use App\Entity\Variant\Variant;
use App\Entity\Feature\FeatureValue;
use App\Interface\Product\ProductManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;

class ProductManager implements ProductManagerInterface
{
    const validUpdates = ['name', 'category', 'description', 'brand'];

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getRequestBody(Request $req)
    {
        return json_decode($req->getContent(), true);
    }

    public function normalizeArray(array $array): array
    {
        if (array_key_exists("name", $array) == false) $array['name'] = null;
        if (array_key_exists("description", $array) == false) $array['description'] = null;
        if (array_key_exists("active", $array) == false) $array['active'] = true;
        if (array_key_exists("brand", $array) == false) $array['brand'] = null;
        if (array_key_exists("category", $array) == false) $array['category'] = null;
        return $array;
    }

    public function findOneById(int $id)
    {
        return $this->em->getRepository(Product::class)->findOneById($id);
    }

    public function createEntityFromArray(array $validatedArray): Product
    {
        $product = new Product();
        $product->setName($validatedArray['name']);
        $product->setDescription($validatedArray['description']);
        $product->setActive($validatedArray['active']);
        $brand = $validatedArray['brand'];
        if ($brand != null) $brand = $this->em->getRepository(Brand::class)->findOneById($brand);
        $product->setBrand($brand);
        $category = $validatedArray['category'];
        if ($category != null) $category = $this->em->getRepository(Category::class)->findOneById($validatedArray['category']);
        $product->setCategory($category);
        $this->em->getRepository(Product::class)->add($product, true);
        return $product;
    }

    public function updateEntity(Product $product, array $updates): Product
    {
        if (array_key_exists('category', $updates) == true) {
            $updates['category'] = $this->em->getRepository(Category::class)->findOneById($updates['category']);
        }
        if (array_key_exists('brand', $updates) == true) {
            $updates['brand'] = $this->em->getRepository(Category::class)->findOneById($updates['brand']);
        }
        foreach ($updates as $key => $value) {
            if (in_array($key, self::validUpdates) == false) throw new Exception('invalid operation');
            $product->setWithKeyValue($key, $value);
        }
        $this->em->getRepository(Product::class)->add($product, true);
        return $product;
    }

    public function deleteById(int $id): array
    {
        $product = $this->em->getRepository(Product::class)->findOneById($id);
        $variants = $product->getVariants();
        $variantRepo = $this->em->getRepository(Variant::class);
        foreach ($variants as $variant) {
            $variantRepo->remove($variant, false);
        }
        if (count($product->getVariants()) != 0) throw new Exception('operation failed');
        $this->em->getRepository(Product::class)->remove($product, true);
        return ['message' => 'product deleted'];
    }

    public function addFeature(int $id, array $features): array
    {
        $product = $this->em->getRepository(Product::class)->findOneById($id);
        $validFeatures = self::getValidFeatureValuesByProduct($product);
        foreach ($features as $featureValueId) {
            $featureValue = $this->em->getRepository(FeatureValue::class)->findOneBy(['id' => "$featureValueId"]);
            $featureKeyId = $featureValue->getFeature()->getId();
            if (array_key_exists($featureKeyId, $validFeatures) == false) throw new Exception('invalid feature found');
            if (in_array($featureValueId, $validFeatures[$featureKeyId]) == false) throw new Exception('invalid feature value found');
            $product->addFeatureValue($featureValue);
        }
        $this->em->getRepository(Product::class)->add($product, true);
        return ['message' => 'features added'];
    }

    public function getValidFeatureValuesByProduct(Product $product): array
    {
        $category = $product->getCategory();
        $validFeatures = [];
        foreach ($category->getFeatures() as $feature) {
            $featureId = $feature->getId();
            $featureValues = $feature->getFeatureValues();
            $featureValueIds = [];
            foreach ($featureValues as $featureValue) {
                $featureValueIds[] = $featureValue->getId();
            }
            $validFeatures[$featureId] = $featureValueIds;
        }
        return $validFeatures;
    }

    public function removeFeature(int $id, array $features): array
    {
        $product = $this->em->getRepository(Product::class)->findOneById($id);
        foreach ($features as $featureValue) {
            $itemValue = $this->em->getRepository(FeatureValue::class)->findOneBy(['id' => $featureValue]);
            $product->removeFeatureValue($itemValue);
        }
        $this->em->getRepository(Product::class)->add($product, true);
        return ['message' => 'features removed'];
    }

    public function toggleActivity(int $id, bool $active): array
    {
        $product = $this->em->getRepository(Product::class)->findOneById($id);
        foreach ($product->getVariants() as $variant) {
            $variant->setStatus($active);
            $this->em->persist($variant);
        }
        $product->setActive($active);
        $this->em->getRepository(Product::class)->add($product, true);
        return ['message' => 'product status changed'];
    }

    public function findBrandProducts(int $id, array $options): array
    {
        return $this->em->getRepository(Product::class)->findProductsByBrandId($id, $options);
    }

    public function findCategoryProducts(int $id, array $options): array
    {
        return $this->em->getRepository(Product::class)->findProductsByCategoryId($id, $options);
    }

    public function findById(int $id): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneById($id);
    }
}
