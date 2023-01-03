<?php
/**
 * Created by PhpStorm.
 * User: kostya
 * Date: 11/20/18
 * Time: 5:42 PM
 */

namespace Remarkety\Mgconnector\Controller\Frontend;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseFactory;
use Magento\Paypal\Model\Express\Checkout\Factory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Framework\Controller\ResultFactory;
use \Magento\Framework\Exception\NotFoundException;
use Psr\Log\LoggerInterface;

class Recovery extends \Magento\Framework\App\Action\Action
{
    protected $quoteFactory;
    protected $checkoutSession;
    protected $responseFactory;
    protected $resultRedirect;
    protected $context;
    protected $recoveryHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        Context $context,
        QuoteFactory $quoteFactory,
        Session $checkoutSession,
        ResponseFactory $responseFactory,
        \Remarkety\Mgconnector\Helper\Recovery $recoveryHelper,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->quoteFactory = $quoteFactory;
        $this->checkoutSession = $checkoutSession;
        $this->responseFactory = $responseFactory;
        $this->resultRedirect = $context->getResultFactory();
        $this->context = $context;
        $this->recoveryHelper = $recoveryHelper;
        parent::__construct($context);
    }

    /**
     * Recovery cart
     * route url - {store_url}/mgconnector/frontend/recovery/cart/{hash_cart}
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws NotFoundException
     */
    public function execute()
    {
        try {
            $hashCart = $this->getRequest()->getParam('cart');
            if (!$hashCart) {
                throw new NotFoundException(__('Missing cart id in URL'));
            }
            $quote_id = $this->recoveryHelper->decodeQuoteId($hashCart);
            if (!is_int($quote_id)) {
                throw new NotFoundException(__('Invalid cart id from URL'));
            }

            $current_quote = $this->checkoutSession->getQuoteId();
            if ($current_quote != $quote_id) {
                $quote = $this->quoteFactory->create()->load($quote_id);
                if ($quote && $quote->getId() == $quote_id) {
                    $quote_id = $this->recoveryHelper->quoteRestore($quote);
                    $this->checkoutSession->setQuoteId($quote_id);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        $params = $this->getRequest()->getParams();
        if(array_key_exists('cart', $params)){
            unset($params['cart']);
        }

        $query = http_build_query($params);
        $resultRedirect = $this->resultRedirect->create(ResultFactory::TYPE_REDIRECT);
        $redirectUri = '/checkout/cart/index?' . $query;

        // Preserve any additional URI before our /mgconnector route when redirecting to the cart checkout page
        // Most common example is the language param, e.g. https://website.com/he/mgconnector/frontend/recovery/cart/<hash>
        $urlInterface = ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
        $currentUri = parse_url($urlInterface->getCurrentUrl(), PHP_URL_PATH);
        $additionalUri = substr($currentUri, 0, strpos($currentUri, '/mgconnector'));
        if ($additionalUri) {
            $redirectUri = $additionalUri . $redirectUri;
        }

        $resultRedirect->setUrl($redirectUri);
        return $resultRedirect;
    }
}
