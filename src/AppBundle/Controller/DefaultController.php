<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Stat;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    private $exCategoriesIds = array();
    private $menuItems = array();
    private $metaTags = array();
    private $productsPerPage = 20;
    private $breadcrumbsCategories = array();

    public function __construct()
    {
        $this->getMetaItems();
    }

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $resultProducts = array();
        $notNeedArray = array(0);
        $em = $this->getDoctrine()->getManager();
        $qb = $em->createQueryBuilder();
        $products = $em
            ->getRepository('AppBundle:Product')
            ->findBy(
                array(
                    'ourChoice' => true,
                    'isDelete' => false
                ),
                array(),
                10
            );
        if (count($products) < 10) {
            foreach ($products as $product) {
                $notNeedArray[] = $product->getId();
            }
            $needCount = 10 - count($products);
            $qb->select('Product')
                ->from('AppBundle:Product', 'Product')
                ->where('Product.id NOT IN (:notNeedArray)')
                ->andWhere('Product.isDelete = 0')
                ->setParameter('notNeedArray', $notNeedArray)
                ->setMaxResults($needCount);
            $query = $qb->getQuery();
            $moreProducts = $query->getResult();
            $products = array_merge($products, $moreProducts);
        }
        foreach ($products as $product) {
            $resultProducts[] = array(
                'name' => $product->getName(),
                'model' => $product->getModel(),
                'pictures' => $product->getPictures(),
                'id' => $product->getId(),
                'url' => $product->getUrl(),
                'price' => $product->getPrice(),
                'alias' => $product->getAlias(),
            );
        }

        $this->getMenuItems();
        return $this->render('AppBundle:Default:index.html.twig', array(
            'products' => $resultProducts,
            'vendors' => $this->menuItems['vendors'],
            'menuItems' => $this->menuItems,
            'metaTags' => $this->metaTags,
            'paginatorData' => null,
        ));
    }

    /**
     * @Route("/shop/description/{alias}", name="shop_description_route")
     */
    public function siteDescriptionAction($alias)
    {
        $em = $this->getDoctrine()->getManager();
        $site = $em
            ->getRepository('AppBundle:Site')
            ->findOneBy(array('alias' => $alias));
        $this->metaTags['metaTitle'] = 'Описание магазина ' . $site->getTitle() . '. Купить товары "' . $site->getTitle() . '" с доставкой по России.';

        $qb = $em->createQueryBuilder();
        $qb->select('Vendor, count(Vendor) as cnt')
            ->from('AppBundle:Product', 'Product')
            ->leftJoin('AppBundle:Vendor', 'Vendor')
            ->where('Vendor = Product.vendor')
            ->andWhere('Vendor.site = :site')
            ->andWhere('Vendor.isActive = 1')
            ->setParameter('site', $site)
            ->groupBy('Vendor')
            ->orderBy('cnt', 'DESC')
            ->setFirstResult(0)
            ->setMaxResults(12);
        $query = $qb->getQuery();
        $vendors = $query->getResult();

        $this->getMenuItems();
        return $this->render('AppBundle:Default:site.description.html.twig', array(
                'site' => $site,
                'metaTags' => $this->metaTags,
                'menuItems' => $this->menuItems,
                'vendors' => $vendors
            )
        );
    }

    /**
     * @Route("/shop/{alias}/{page}", name="shop_route")
     */
    public function siteAction($alias, $page = 1)
    {
        $this->metaTags['metaRobots'] = 'noindex';
        $em = $this->getDoctrine()->getManager();
        $site = $em
            ->getRepository('AppBundle:Site')
            ->findOneBy(array('alias' => $alias));

        $qb = $em->createQueryBuilder();
        $qb->select('Vendor, count(Vendor) as cnt')
            ->from('AppBundle:Product', 'Product')
            ->leftJoin('AppBundle:Vendor', 'Vendor')
            ->where('Vendor = Product.vendor')
            ->andWhere('Vendor.site = :site')
            ->andWhere('Vendor.isActive = 1')
            ->setParameter('site', $site)
            ->groupBy('Vendor')
            ->orderBy('cnt', 'DESC')
            ->setFirstResult(0)
            ->setMaxResults(12);
        $query = $qb->getQuery();
        $vendors = $query->getResult();

        $qb = $em->createQueryBuilder();
        $qb->select('Product')
            ->from('AppBundle:Product', 'Product')
            ->where('Product.site = :site')
            ->andWhere('Product.isDelete = 0')
            ->setParameter('site', $site);
        $query = $qb->getQuery()
            ->setFirstResult($this->productsPerPage * ($page - 1))
            ->setMaxResults($this->productsPerPage);
        $products = new Paginator($query, $fetchJoinCollection = true);

        $productsCount = count($products);
        $paginatorPagesCount = ceil($productsCount / $this->productsPerPage);
        $path = "/shop/$alias/";
        if ($productsCount <= $this->productsPerPage) {
            $paginatorData = null;
        } else {
            $paginatorData = $this->getPaginatorData($paginatorPagesCount, $page, 1, 5, $path);
        }

        $this->getMenuItems();
        return $this->render('AppBundle:Default:site.html.twig', array(
                'site' => $site,
                'metaTags' => $this->metaTags,
                'menuItems' => $this->menuItems,
                'products' => $products,
                'paginatorData' => $paginatorData,
                'vendors' => $vendors
            )
        );
    }

    /**
     * @Route("/vendor/{alias}/{page}", name="vendor_route")
     */
    public function vendorAction($alias, $page = 1)
    {
        $this->metaTags['metaRobots'] = 'nofollow';
        $em = $this->getDoctrine()->getManager();
        $vendors = $em
            ->getRepository('AppBundle:Vendor')
            ->findBy(array(
                'alias' => $alias,
                'isActive' => 1
            ));
        if (empty($vendors)) {
            throw $this->createNotFoundException();
        }
        foreach ($vendors as $vendor) {
            $vendorIds[] = $vendor->getId();
            $this->metaTags['metaTitle'] = 'Купить ' . $vendor->getName() . ' со скидкой в интернет-магазине. Доставка по РФ';
        }
        $qb = $em->createQueryBuilder();
        $qb->select('Product')
            ->from('AppBundle:Product', 'Product')
            ->where('Product.vendor IN (:vendorIds)')
            ->andWhere('Product.isDelete = 0')
            ->setParameter('vendorIds', $vendorIds);
        $query = $qb->getQuery()
            ->setFirstResult($this->productsPerPage * ($page - 1))
            ->setMaxResults($this->productsPerPage);
        $products = new Paginator($query, $fetchJoinCollection = true);

        $productsCount = count($products);
        $paginatorPagesCount = ceil($productsCount / $this->productsPerPage);
        $path = "/vendor/$alias/";
        if ($productsCount <= $this->productsPerPage) {
            $paginatorData = null;
        } else {
            $paginatorData = $this->getPaginatorData($paginatorPagesCount, $page, 1, 5, $path);
        }
        $this->getMenuItems();
        return $this->render('AppBundle:Default:vendor.html.twig', array(
                'products' => $products,
                'paginatorData' => $paginatorData,
                'vendor' => $vendors[0],
                'metaTags' => $this->metaTags,
                'menuItems' => $this->menuItems
            )
        );
    }

    /**
     * @Route("/category/{alias}/{page}", name="category_route")
     */
    public function categoryAction($alias, $page = 1)
    {
        $this->metaTags['metaRobots'] = 'nofollow';
        $em = $this->getDoctrine()->getManager();
        $category = $em
            ->getRepository('AppBundle:Category')
            ->findOneBy(array(
                'alias' => $alias,
                'isActive' => 1
            ));
        if (!$category) {
            throw $this->createNotFoundException();
        }
        $this->metaTags['metaTitle'] = 'Купить ' . mb_strtolower($category->getName(), 'UTF-8') . ' с доставкой по России.';
        if ($category->getSeoDescription()) {
            $this->metaTags['metaDescription'] = $category->getSeoDescription();
        }

        $externalCategories = $category->getExternalCategories();
        foreach ($externalCategories as $externalCategory ) {
            $this->exCategoriesIds[] = $externalCategory->getExternalId();
        }
        $childCategoriesIds = $this->getChildCategoriesIds($category->getId());
        $qb = $em->createQueryBuilder();
        $qb->select('Product')
            ->from('AppBundle:Product', 'Product')
            ->where('Product.category IN (:exCategoriesIds)')
            ->andWhere('Product.isDelete = 0')
            ->setParameter('exCategoriesIds', $childCategoriesIds);
        $query = $qb->getQuery()
            ->setFirstResult($this->productsPerPage * ($page - 1))
            ->setMaxResults($this->productsPerPage);
        $products = new Paginator($query, $fetchJoinCollection = true);

        $productsCount = count($products);
        $paginatorPagesCount = ceil($productsCount / $this->productsPerPage);
        $path = "/category/$alias/";
        if ($productsCount <= $this->productsPerPage) {
            $paginatorData = null;
        } else {
            $paginatorData = $this->getPaginatorData($paginatorPagesCount, $page, 1, 5, $path);
        }

        $this->getMenuItems();
        $media = $category->getMedia();
        if ($media) {
            $provider = $this->container->get($media->getProviderName());
            $url = $provider->generatePublicUrl($media, 'reference');
            $this->menuItems['slideUrl'] = $url;
        }
        if (!empty($category->getSeoDescription())) {
            $this->menuItems['slideText'] = $category->getSeoDescription();
        }
        return $this->render('AppBundle:Default:category.html.twig', array(
                'products' => $products,
                'paginatorData' => $paginatorData,
                'category' => $category,
                'metaTags' => $this->metaTags,
                'menuItems' => $this->menuItems
            )
        );
    }

    /**
     * @Route("/exCategory/{id}/{page}", name="ex_category_route")
     */
    public function exCategoryAction($id, $page = 1)
    {
        $em = $this->getDoctrine()->getManager();
        $exCategory = $em
            ->getRepository('AppBundle:ExternalCategory')
            ->findOneBy(array(
                'id' => $id,
                'isActive' => 1
            ));
        if (!$exCategory) {
            throw $this->createNotFoundException();
        }
        $this->metaTags['metaTitle'] = 'Купить ' . mb_strtolower($exCategory->getName(), 'UTF-8') . ' с доставкой по России.';
        $parentId = $exCategory->getParentId();
        $parentCategory = $em
            ->getRepository('AppBundle:ExternalCategory')
            ->findOneBy(array(
                'externalId' => $parentId,
                'isActive' => 1
            ));
        if ($parentCategory) {
            $internalParentCategory = $parentCategory->getInternalParentCategory();
        }
        $childCategoriesIds = $this->getChildCategoriesIds($exCategory->getExternalId());
        $childCategoriesIds[] = $exCategory->getId();
        $qb = $em->createQueryBuilder();
        $qb->select('Product')
            ->from('AppBundle:Product', 'Product')
            ->where('Product.category IN (:childCategoriesIds)')
            ->andWhere('Product.isDelete = 0')
            ->setParameter('childCategoriesIds', $childCategoriesIds);
        $query = $qb->getQuery()
            ->setFirstResult($this->productsPerPage * ($page - 1))
            ->setMaxResults($this->productsPerPage);
        $products = new Paginator($query, $fetchJoinCollection = true);

        $productsCount = count($products);
        $paginatorPagesCount = ceil($productsCount / $this->productsPerPage);
        $path = "/exCategory/$id/";
        if ($productsCount <= $this->productsPerPage) {
            $paginatorData = null;
        } else {
            $paginatorData = $this->getPaginatorData($paginatorPagesCount, $page, 1, 5, $path);
        }

        $this->getMenuItems();
        $returnArray = array(
            'products' => $products,
            'paginatorData' => $paginatorData,
            'exCategory' => $exCategory,
            'metaTags' => $this->metaTags
        );
        if (isset($internalParentCategory)) {
            $media = $internalParentCategory->getMedia();
            if ($media) {
                $provider = $this->container->get($media->getProviderName());
                $url = $provider->generatePublicUrl($media, 'reference');
                $this->menuItems['slideUrl'] = $url;
            }
            if (!empty($internalParentCategory->getSeoDescription())) {
                $this->menuItems['slideText'] = $internalParentCategory->getSeoDescription();
            }
            if (!empty($parentCategory)) {
                $exCategories = $em
                    ->getRepository('AppBundle:ExternalCategory')
                    ->findBy(array('parentId' => $parentCategory->getExternalId()));
                if ($exCategories) {
                    $returnArray['exCategories'] = $exCategories;
                }
            }
        }
        $returnArray['menuItems'] = $this->menuItems;
        return $this->render('AppBundle:Default:exCategory.html.twig', $returnArray);
    }

    /**
     * @Route("/product/{id}", name="product_route")
     */
    public function productAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $product = $em
            ->getRepository('AppBundle:Product')
            ->findOneBy(array('id' => $id));
        if (!$product) {
            throw $this->createNotFoundException('The product does not exist');
        }
        $productAlias = $product->getAlias();
        if (!$productAlias) {
            throw $this->createNotFoundException('The product does not exist');
        }
        return $this->redirectToRoute('product_detail_route', array('alias' => $productAlias));
    }

    /**
     * @Route("/product/detail/{alias}", name="product_detail_route")
     */
    public function productDetailAction($alias)
    {
        $likeProducts = array();
        $categoryProducts = array();
        $em = $this->getDoctrine()->getManager();
        $product = $em
            ->getRepository('AppBundle:Product')
            ->findOneBy(array('alias' => $alias));
        if (!$product) {
            throw $this->createNotFoundException('The product does not exist');
        }
        if ($product->getIsDelete()) {
            $this->metaTags['metaRobots'] = 'noindex, nofollow';
        }
        $productCategory = $product->getCategory();
        $productCategoryName = '';
        if ($productCategory) {
            $productCategoryName = $productCategory->getName();
            $categoryProducts = $productCategory->getProducts();
        }
        $productVendor = $product->getVendor();
        $productVendorName = '';
        if ($productVendor) {
            $productVendorName = $productVendor->getName();
        }
        foreach ($categoryProducts as $categoryProduct) {
            if (count($likeProducts) < 5) {
                if ($categoryProduct->getId() != $product->getId() && !$categoryProduct->getIsDelete()) {
                    $likeProducts[] = $categoryProduct;
                }
            }
        }

        if (!empty($productCategoryName)) {
            $productKeywords[] =  $productCategoryName . ' купить';
            $productFullName[] = $productCategoryName;
        }
        if (!empty($productVendorName)) {
            $productKeywords[] =  $productVendorName . ' купить';
            $productFullName[] = $productVendorName;
        }
        $productFullName[] = $product->getModel();
        $productFullName = array_filter($productFullName);
        $this->getMenuItems();
        $this->metaTags['metaTitle'] = 'Описание и цена ' . mb_strtolower($product->getName(), 'UTF-8') . '. Купить ' . mb_strtolower(implode(' | ', $productFullName), 'UTF-8') . ' с доставкой по России.';
        $this->metaTags['metaDescription'] = substr($product->getDescription(), 0, 400);
        $productKeywords[] =  $product->getName() . ' ' . $product->getModel() . ' купить';
        $this->metaTags['metaKeywords'] .= ',' . implode(',', $productKeywords);
        $this->getBreadcrumbs($product, 'product');
        return $this->render('AppBundle:Default:product.description.html.twig', array(
                'product' => $product,
                'metaTags' => $this->metaTags,
                'likeProducts' => $likeProducts,
                'paginatorData' => null,
                'menuItems' => $this->menuItems,
                'breadcrumbsCategories' => array_reverse($this->breadcrumbsCategories)
            )
        );
    }

    /**
     * @Route("/filter/{vendorAlias}/{categoryId}/{page}", name="filter_route")
     */
    public function filterAction($vendorAlias = '', $categoryId = 0, $page = 1)
    {
        if ($categoryId == 0 && $vendorAlias == '') {
            return $this->redirectToRoute('homepage');
        }
        $em = $this->getDoctrine()->getManager();
        $vendorIds = array();
        $vendors = $em
            ->getRepository('AppBundle:Vendor')
            ->findBy(array(
                'alias' => $vendorAlias,
                'isActive' => 1
            ));
        $exCategory = $em
            ->getRepository('AppBundle:ExternalCategory')
            ->findOneBy(array(
                'id' => $categoryId,
                'isActive' => 1
            ));
        if (!$exCategory || empty($vendors)) {
            throw $this->createNotFoundException();
        }
        foreach ($vendors as $vendor) {
            $vendorIds[] = $vendor->getId();
            $this->metaTags['metaTitle'] = 'Купить ' . mb_strtolower ($exCategory->getName(), 'UTF-8') . ' ' . $vendor->getName() . ' со скидкой в интернет-магазине. Доставка по РФ';
            $this->metaTags['metaDescription'] = 'Выбрать и купить ' . mb_strtolower ($exCategory->getName(), 'UTF-8') . ' ' . $vendor->getName() . ' со скидкой в интернет-магазине. Доставка по РФ';
        }
        $qb = $em->createQueryBuilder();
        $qb->select('Product')
            ->from('AppBundle:Product', 'Product')
            ->where('Product.vendor IN (:vendorIds)')
            ->andWhere('Product.isDelete = 0')
            ->andWhere('Product.category = :category')
            ->setParameter('category', $categoryId)
            ->setParameter('vendorIds', $vendorIds);
        $query = $qb->getQuery()
            ->setFirstResult($this->productsPerPage * ($page - 1))
            ->setMaxResults($this->productsPerPage);
        $products = new Paginator($query, $fetchJoinCollection = true);

        $productsCount = count($products);
        if ($productsCount <= 0) {
            $qb = $em->createQueryBuilder();
            $qb->select('Product')
                ->from('AppBundle:Product', 'Product')
                ->where('Product.vendor IN (:vendorIds)')
                ->andWhere('Product.isDelete = 0')
                ->setParameter('vendorIds', $vendorIds);
            $query = $qb->getQuery()
                ->setFirstResult($this->productsPerPage * ($page - 1))
                ->setMaxResults($this->productsPerPage);
            $products = new Paginator($query, $fetchJoinCollection = true);
            $productsCount = count($products);
            if ($productsCount > 0) {
                return $this->redirectToRoute('vendor_route', array('alias' => $vendorAlias));
            } else {
                $qb = $em->createQueryBuilder();
                $qb->select('Product')
                    ->from('AppBundle:Product', 'Product')
                    ->andWhere('Product.isDelete = 0')
                    ->andWhere('Product.category = :category')
                    ->setParameter('category', $categoryId);
                $query = $qb->getQuery()
                    ->setFirstResult($this->productsPerPage * ($page - 1))
                    ->setMaxResults($this->productsPerPage);
                $products = new Paginator($query, $fetchJoinCollection = true);
                $productsCount = count($products);
                if ($productsCount > 0 ) {
                    return $this->redirectToRoute('ex_category_route', array('id' => $categoryId));
                } else {
                    return $this->redirectToRoute('homepage');
                }
            }
        }
        $paginatorPagesCount = ceil($productsCount / $this->productsPerPage);
        $path = "/filter/$vendorAlias/$categoryId/";
        if ($productsCount <= $this->productsPerPage) {
            $paginatorData = null;
        } else {
            $paginatorData = $this->getPaginatorData($paginatorPagesCount, $page, 1, 5, $path);
        }
        $this->getMenuItems();
        $returnArray = array(
            'products' => $products,
            'paginatorData' => $paginatorData,
            'exCategory' => $exCategory,
            'vendor' => $vendors[0],
            'metaTags' => $this->metaTags
        );
        $returnArray['menuItems'] = $this->menuItems;
        return $this->render('AppBundle:Default:filter.html.twig', $returnArray);
    }

    /**
     * @Route("/product/buy/{alias}", name="product_buy_route")
     */
    public function productBuyAction($alias, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $product = $em
            ->getRepository('AppBundle:Product')
            ->findOneBy(array('alias' => $alias));
        if (!$product) {
            throw $this->createNotFoundException('The product does not exist');
        }
        $newStat = new Stat();
        $newStat->setProductId($product->getId());
        if ($request->getClientIp()) {
            $newStat->setClientIp($request->getClientIp());
        }
        $em->persist($newStat);
        $em->flush();
        return $this->redirect($product->getURl());
    }

    private function getChildCategoriesIds($parentCategoryId)
    {
        $resultCategoriesIds = array();
        $parentCategoryIds = array();
        $em = $this->getDoctrine()->getManager();
        $qb = $em->createQueryBuilder();
        $qb->select('ExCategory.externalId')
            ->from('AppBundle:ExternalCategory', 'ExCategory')
            ->where('ExCategory.internalParentCategory = :parentCategoryId')
            ->orWhere('ExCategory.parentId = :parentCategoryId')
            ->andWhere('ExCategory.isActive = 1')
            ->setParameter('parentCategoryId', $parentCategoryId);
        $query = $qb->getQuery();
        $exCategoriesIds = $query->getResult();
        foreach ($exCategoriesIds as $exCategoriesId) {
            $parentCategoryIds[] = $exCategoriesId['externalId'];
        }
        $qb = $em->createQueryBuilder();
        $qb->select('ExCat.id')
            ->from('AppBundle:ExternalCategory', 'ExCat')
            ->where('ExCat.parentId IN (:parentCategoryIds)')
            ->orWhere('ExCat.externalId IN (:parentCategoryIds)')
            ->andWhere('ExCat.isActive = 1')
            ->setParameter('parentCategoryIds', $parentCategoryIds);
        $query = $qb->getQuery();
        $exCatIds = $query->getResult();
        foreach ($exCatIds as $exCategoriesId) {
            $resultCategoriesIds[] = $exCategoriesId['id'];
        }
        return $resultCategoriesIds;
    }

    private function getMenuItems()
    {
        $em = $this->getDoctrine()->getManager();
        $qb = $em->createQueryBuilder();
        $qb->select('Category.alias, Category.name, Category.id')
            ->from('AppBundle:Category', 'Category')
            ->where('Category.isActive = 1')
            ->groupBy('Category.alias');
        $query = $qb->getQuery();
        $resultCategories = $query->getResult();
        foreach ($resultCategories as $resultCategory) {
            $count = 0;
            $childCategoriesIds = $this->getChildCategoriesIds($resultCategory['id']);
            $qb = $em->createQueryBuilder();
            $qb->select('exCategory.id, exCategory.name, count(Product.id) as cnt')
                ->from('AppBundle:ExternalCategory', 'exCategory')
                ->leftJoin('exCategory.products', 'Product')
                ->where('exCategory.id IN (:childCategoriesIds)')
                ->andWhere('exCategory.isActive = 1')
                ->having('cnt > 0')
                ->orderBy('cnt', 'DESC')
                ->setParameter('childCategoriesIds', $childCategoriesIds);
            $query = $qb->getQuery();
            $resultChildCategories = $query->getResult();
            foreach ($resultChildCategories as $resultChildCategory) {
                $count += $resultChildCategory['cnt'];
            }
            $this->menuItems['categories'][$count] = $resultCategory;
        }
        krsort($this->menuItems['categories']);

        $this->menuItems['sites'] = $em
            ->getRepository('AppBundle:Site')
            ->findAll();
        $qb = $em->createQueryBuilder();

        $qb->select('Vendor.alias, Vendor.name, count(p.id) as cnt')
            ->from('AppBundle:Vendor', 'Vendor')
            ->leftJoin('Vendor.products', 'p')
            ->where('Vendor.isActive = 1')
            ->having('cnt > 450')
            ->groupBy('Vendor.alias')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(25);
        $query = $qb->getQuery();
        $resultVendors = $query->getResult();
        foreach ($resultVendors as $resultVendor) {
            $this->menuItems['vendors'][] = $resultVendor;
        }
        $this->menuItems['slideUrl'] = '/bundles/app/images/middleBlockPicture.png';
        $this->menuItems['slideText'] = 'Современная одежда для Вашей семьи.';
    }

    private function getMetaItems()
    {
        $this->metaTags['metaTitle'] = 'Купить современную одежду с доставкой. Свитшоты, пуловеры, платья и шорты.';
        $this->metaTags['metaDescription'] = 'У нас Вы найдете качественную современную одежду для всей семьи!';
        $this->metaTags['metaKeywords'] = 'cвитшоты, пуловеры, платья, шорты, молодежная и детская одежда';
        $this->metaTags['metaRobots'] = 'all';
    }

    private function getPaginatorData($itemsCount, $currentPage, $limit, $midRange, $path = '/page/')
    {
        $paginator = new \AppBundle\Helpers\Paginator($itemsCount, $currentPage, $limit, $midRange);
        return array(
            'paginator' => $paginator,
            'path' => $path,
        );
    }

    private function getBreadcrumbs($item, $type)
    {
        switch ($type) {
            case 'product':
                $itemParentCategory = $item->getCategory();
                if ($itemParentCategory) {
                    $this->breadcrumbsCategories[] = $itemParentCategory;
                    if ($itemParentCategory->getParentId() != 0) {
                        $this->getBreadcrumbs($itemParentCategory, 'exCategory');
                    }
                }
                break;
            case 'exCategory':
                $em = $this->getDoctrine()->getManager();
                $itemParentCategory = $em
                    ->getRepository('AppBundle:ExternalCategory')
                    ->findOneBy(array(
                        'externalId' => $item->getParentId(),
                        'isActive' => 1
                    ));
                if ($itemParentCategory) {
                    $this->breadcrumbsCategories[] = $itemParentCategory;
                    if ($itemParentCategory->getParentId() != 0) {
                        $this->getBreadcrumbs($itemParentCategory, 'exCategory');
                    } else {
                        $internalParentCategory = $itemParentCategory->getInternalParentCategory();
                        if ($internalParentCategory) {
                            array_pop($this->breadcrumbsCategories);
                            $this->breadcrumbsCategories[] = $internalParentCategory;
                        }
                    }
                }
                break;
        }
    }
}
