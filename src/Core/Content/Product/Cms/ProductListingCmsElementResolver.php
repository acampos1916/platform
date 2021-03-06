<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductListingCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @var AbstractProductListingRoute
     */
    private $listingRoute;

    public function __construct(AbstractProductListingRoute $listingRoute)
    {
        $this->listingRoute = $listingRoute;
    }

    public function getType(): string
    {
        return 'product-listing';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $data = new ProductListingStruct();
        $slot->setData($data);

        $request = $resolverContext->getRequest();
        $context = $resolverContext->getSalesChannelContext();

        if (Feature::isActive('FEATURE_NEXT_5983')) {
            $this->addDefaultSorting($request, $slot);
            $this->restrictSortings($request, $slot);
        }

        $navigationId = $this->getNavigationId($request, $context);

        $criteria = new Criteria();
        $criteria->setTitle('cms::product-listing');

        $listing = $this->listingRoute
            ->load($navigationId, $request, $context, $criteria)
            ->getResult();

        $data->setListing($listing);
    }

    private function getNavigationId(Request $request, SalesChannelContext $salesChannelContext): string
    {
        if ($navigationId = $request->get('navigationId')) {
            return $navigationId;
        }

        $params = $request->attributes->get('_route_params');

        if ($params && isset($params['navigationId'])) {
            return $params['navigationId'];
        }

        return $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
    }

    private function addDefaultSorting(Request $request, CmsSlotEntity $slot): void
    {
        if ($request->get('order')) {
            return;
        }

        $config = $slot->getTranslation('config');

        if ($config && isset($config['defaultSorting'])) {
            $request->request->set('order', $config['defaultSorting']);
        }
    }

    private function restrictSortings(Request $request, CmsSlotEntity $slot): void
    {
        $config = $slot->getTranslation('config');

        if (!$config || !isset($config['availableSortings']) || !isset($config['availableSortings']['value'])) {
            return;
        }

        $request->request->set('availableSortings', $config['availableSortings']['value']);
    }
}
