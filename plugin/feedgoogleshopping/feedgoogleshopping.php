<?php

/**
* Plugin Name: Feed Export for Google Shopping
* Version: 1.0.0
* Author: Francisco Antonio Martín Herzog
* Requires Plugins: woocommerce
*/

if (!defined('ABSPATH')) {
	exit;
}

class FeedGoogleShoppingWC
{
	const QUERY_VAR = 'fgs_feed';

	public function __construct()
	{
		add_action('init', [$this, 'addRewriteRule']);
		add_filter('query_vars', [$this, 'addQueryVar']);
        	add_action('template_redirect', [$this, 'maybeRenderFeed']);
        	register_activation_hook(__FILE__, [$this, 'onActivate']);
        	register_deactivation_hook(__FILE__, [$this, 'onDeactivate']);
	}

	public function addRewriteRule()
    {
        add_rewrite_rule('^feed/google-shopping/?$', 'index.php?'.self::QUERY_VAR.'=1', 'top');
    }

    public function addQueryVar($vars)
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public function onActivate()
    {
        $this->addRewriteRule();
        flush_rewrite_rules();
    }

    public function onDeactivate()
    {
        flush_rewrite_rules();
    }

    public function maybeRenderFeed()
    {
        if (!get_query_var(self::QUERY_VAR)) {
            return;
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo $this->buildFeed();
        exit;
    }

    private function buildFeed()
    {
        $storeName = get_bloginfo('name');
        $storeUrl = home_url('/');
        $currency = get_woocommerce_currency();

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $xml->startElement('channel');
        $xml->writeElement('title', $storeName.' Product Feed');
        $xml->writeElement('link', $storeUrl);
        $xml->writeElement('description', 'Google Shopping product feed for '.$storeName);

        $products = wc_get_products([
            'status' => 'publish',
            'limit' => -1,
        ]);

        foreach ($products as $product) {
            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $variationId) {
                    $variation = wc_get_product($variationId);
                    if ($variation) {
                        $this->writeItem($xml, $variation, $product, $currency);
                    }
                }
            } else {
                $this->writeItem($xml, $product, $product, $currency);
            }
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * @param XMLWriter $xml
     * @param WC_Product $item The sellable entity (variation or simple product)
     * @param WC_Product $parent The parent product (same as $item for simple products)
     */
    private function writeItem(XMLWriter $xml, $item, $parent, $currency)
    {
        $price = $item->get_price();
        $availability = $item->is_in_stock() ? 'in stock' : 'out of stock';
        $imageId = $item->get_image_id() ?: $parent->get_image_id();
        $imageUrl = $imageId ? wp_get_attachment_image_url($imageId, 'full') : '';
        $sku = $item->get_sku() ?: $parent->get_sku();
        $description = $parent->get_short_description() ?: $parent->get_description();

        $xml->startElement('item');
        $xml->writeElement('g:id', (string) $item->get_id());
        $xml->writeElement('g:title', $parent->get_name());
        $xml->writeElement('g:description', wp_strip_all_tags($description));
        $xml->writeElement('g:link', $item->get_permalink());
        $xml->writeElement('g:image_link', $imageUrl);
        $xml->writeElement('g:availability', $availability);
        $xml->writeElement('g:price', number_format((float) $price, 2, '.', '').' '.$currency);
        $xml->writeElement('g:brand', get_bloginfo('name'));
        $xml->writeElement('g:condition', 'new');
        if ($sku) {
            $xml->writeElement('g:mpn', $sku);
        }
        $xml->endElement(); // item
    }
}

new FeedGoogleShoppingWC();
