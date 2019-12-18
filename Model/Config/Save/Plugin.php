<?php
/**
 * Magento 2 extensions for Clearpay Payment
 *
 * @author Clearpay
 * @copyright 2016-2019 Clearpay https://www.clearpay.co.uk
 */
namespace Clearpay\Clearpay\Model\Config\Save;

/**
 * Class Plugin
 * @package Clearpay\Clearpay\Model\Config\Save
 */
class Plugin
{
    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;
    protected $clearpayTotalLimit;
    protected $resourceConfig;
    protected $requested;
    protected $storeManager;
    protected $request;
    protected $messageManager;

    /**
     * Plugin constructor.
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Clearpay\Clearpay\Model\Adapter\ClearpayTotalLimit $clearpayTotalLimit
     * @param \Magento\Config\Model\ResourceModel\Config $resourceConfig
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Clearpay\Clearpay\Model\Adapter\ClearpayTotalLimit $clearpayTotalLimit,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->clearpayTotalLimit = $clearpayTotalLimit;
        $this->resourceConfig = $resourceConfig;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->messageManager = $messageManager;
    }

    /**
     * @param \Magento\Config\Model\Config $subject
     * @param \Closure $proceed
     */
    public function aroundSave(
        \Magento\Config\Model\Config $subject,
        \Closure $proceed
    ) {
        
        //first saving run to eliminate possibilities of conflicting config results
        $proceed();

        if (class_exists('\Clearpay\Clearpay\Model\Payovertime')) {

            try {
                $configRequest = $subject->getGroups();
                $this->requested = array_key_exists(\Clearpay\Clearpay\Model\Payovertime::METHOD_CODE, $configRequest);
				
				if ($this->requested) {
					$config_array=$configRequest[\Clearpay\Clearpay\Model\Payovertime::METHOD_CODE]['groups'][\Clearpay\Clearpay\Model\Payovertime::METHOD_CODE . '_basic']['fields'][\Clearpay\Clearpay\Model\Config\Payovertime::ACTIVE];
					
					if(array_key_exists('value',$config_array)){
						
						if($config_array['value'] == '1'){
							$response = $this->clearpayTotalLimit->getLimit();
							$response = $this->jsonHelper->jsonDecode($response->getBody());

							if (!array_key_exists('errorCode', $response)) {
								// default min and max if not provided
								$minTotal = "0";
								$maxTotal = "0";
								
								// understand the response from the API
								$minTotal = array_key_exists('minimumAmount',$response) && isset($response['minimumAmount']['amount']) ? $response['minimumAmount']['amount'] : "0";
								$maxTotal = array_key_exists('maximumAmount',$response) && isset($response['maximumAmount']['amount']) ? $response['maximumAmount']['amount'] : "0";

								//Change the minimum amd maximum to Not applicable if both limits are 0.
								if ($minTotal == "0" && $maxTotal=="0") {
									$minTotal="N/A";
									$maxTotal="N/A";
								}

								// set on config request
								$configRequest[\Clearpay\Clearpay\Model\Payovertime::METHOD_CODE]['groups'][\Clearpay\Clearpay\Model\Payovertime::METHOD_CODE . '_advanced']['fields'][\Clearpay\Clearpay\Model\Config\Payovertime::MIN_TOTAL_LIMIT]['value'] = $minTotal;
								$configRequest[\Clearpay\Clearpay\Model\Payovertime::METHOD_CODE]['groups'][\Clearpay\Clearpay\Model\Payovertime::METHOD_CODE . '_advanced']['fields'][\Clearpay\Clearpay\Model\Config\Payovertime::MAX_TOTAL_LIMIT]['value'] = $maxTotal;

								$subject->setGroups($configRequest);

								return $proceed();
							} else {
								$this->messageManager->addWarningMessage('Clearpay Update Limits Failed. Please check Merchant ID and Key.');
								
							}
						}
					}
				}
			}
            catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return true;
    }
}
