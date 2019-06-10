<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Marketplace extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();

        $this->cart->product_name_safe = false;
    }

    public function index()
    {
        $viewData = array(
            'pageTitle'     => 'Marketplace',
            'pageSubTitle'  => 'Ambilis Mag Shopping!',
            'pageClass'     => 'marketplace-content',
            'accountInfo'   => user_account_details(),
            'jsModules'         => array(
                'marketplace',
            ),
        );

        $page_limit = (get_post('limit') ? ((int) get_post('limit')) : 100);
        $page_start = (int) $this->uri->segment(3);

        $order = 'si.LastUpdate Desc';
        $where = array();

        // SET SEARCH FILTER
        if (get_post('search')) {
            $where['CONCAT(si.Name, " ", si.Description) LIKE ']  = '%' . get_post('search') . '%';
        }

        if (get_post('c')) {
            $where['Category'] = get_post('c');
        }

        if (get_post('sc')) {
            $where['SubCategory'] = get_post('sc');
        }

        if (get_post('b')) {
            $where['TRIM(Manufacturer)'] = get_post('b');
        }

        if (get_post('s')) {
            $storeData = $this->appdb->getRowObject('StoreDetails', get_post('s'), 'Slug');
            $viewData['StoreData'] = $storeData;
            $where['StoreID'] = $storeData->id ?? 0;
            $order = 'si.Name';
        }

        $paginatationData = $this->appdb->getMarketplaceData($page_limit, $page_start, $where, $order);

        $products = array();
        foreach ($paginatationData['data'] as $product) {
            $product = (array) $product;
            if (!isset($sellers[$product['StoreID']])) {
                $sellers[$product['StoreID']] = (array) $this->appdb->getRowObject('StoreDetails', $product['StoreID']);
            }
            $product['Image']  = product_filename($product['Image']);
            $product['seller'] = $sellers[$product['StoreID']];

            $products[] = $product;
        }

        $paginationConfig = array(
            'base_url'      => base_url('marketplace/index'),
            'total_rows'    => $paginatationData['count'],
            'per_page'      => $page_limit,
            'full_tag_open' => '<ul class="pagination pagination-sm no-margin">',
            'first_link'    => false,
            'last_link'     => false,
            ''
        );

        $viewData['products']   = $products;
        $viewData['pagination'] = paginate($paginationConfig);

        $viewData['category']   = $this->appdb->getRowObject('ProductCategories', get_post('c'));
        $viewData['subcategory']   = $this->appdb->getRowObject('ProductSubCategories', get_post('sc'));

        view('main/marketplace/index', $viewData, 'templates/main');
    }

    public function view($code = false)
    {
        $product = $this->appdb->getRowObject('StoreItems', $code, 'Code');
        if ($product) {

            $viewData = array(
                'pageTitle'     => $product->Name,
                'pageSubTitle'  => 'Ambilis Mag Shopping!',
                'accountInfo'   => user_account_details(),
                'jsModules'         => array(
                    'marketplace',
                ),
            );

            $viewData['pageMeta'] = '<meta name="description" content="' .substr(strip_tags($product->Description), 0, 155). '">
                                <meta property="og:title" content="' .$product->Name. '">
                                <meta property="og:url" content="' .site_url('i/'. $product->Code . '-' . slugit($product->Name)). '">
                                <meta property="og:description" content="' .substr(strip_tags($product->Description), 0, 155). '">
                                <meta property="og:image" content="' .public_url('assets/products') . product_filename($product->Image, false). '">
                                <meta property="og:type" content="article" />
                                ';

            $viewData['productData'] = $product;
            $viewData['distribution'] = profit_distribution($product->Price, $product->CommissionValue, $product->CommissionType);

            view('main/marketplace/view', $viewData, 'templates/main');

        } else {
            redirect(site_url('marketplace'));
        }
    }

    public function view_alias($slug = '')
    {
        $code = explode('-', $slug)[0];
        $this->view($code);
    }

    public function view_store($slug = '')
    {
        $_POST['s'] = $slug;
        $_POST['limit'] = 1000;
        $this->index();
    }

    public function brands()
    {
        $viewData = array(
            'pageTitle'         => 'Brands',
            'pageDescription'   => '',
            'accountInfo'       => user_account_details(),
            'jsModules'         => array(
            )
        );

        $where   = array();
        $results = $this->db->query('SELECT DISTINCT(UPPER(TRIM(Manufacturer))) AS `Name`, PartnerImage
                                    FROM StoreItems si
                                    JOIN StoreDetails sd ON si.StoreID = sd.id
                                    WHERE Manufacturer IS NOT NULL AND Manufacturer != ""
                                    AND sd.Status = 1
                                    ORDER BY Name')->result_array();
        $unique = array();
        foreach ($results as $result) {
            $unique[$result['Name']] = $result;
        }
        $viewData['records'] = array_values($unique);

        view('main/marketplace/brands', $viewData, 'templates/main');
    }

    public function cart()
    {

        $viewData = array(
            'pageTitle'     => 'Shopping Cart',
            'pageSubTitle'  => 'Ambilis Mag Shopping!',
            'accountInfo'   => user_account_details(),
            'jsModules'         => array(
                'marketplace',
            ),
        );

        $cart_items = $this->cart->contents();
        foreach ($cart_items as &$item) {
            $product = $this->appdb->getRowObject('StoreItems', $item['id']);
            $seller  = (array) $this->appdb->getRowObject('StoreDetails', $product->StoreID);
            $item['distribution'] = profit_distribution($product->Price, $product->CommissionValue, $product->CommissionType);
            $item['seller'] = $seller['Name'];
        }

        $viewData['items'] = $cart_items;

        // print_data($viewData['items']);

        view('main/marketplace/cart', $viewData, 'templates/main');
    }

    public function checkout()
    {   

        check_authentication();

        // redirect if cart is empty
        if (!$this->cart->total_items()) {
            redirect(site_url('marketplace'));
        }

        $viewData = array(
            'pageTitle'     => 'Checkout',
            'pageSubTitle'  => 'Ambilis Mag Shopping!',
            'accountInfo'   => user_account_details(),
            'jsModules'         => array(
                'marketplace',
                'general'
            ),
        );

        $cart_items = $this->cart->contents();
        $viewData['points'] = 0;
        $viewData['shared'] = 0;
        $viewData['cashback'] = 0; 
        foreach ($cart_items as &$item) {
            $product = $this->appdb->getRowObject('StoreItems', $item['id']);
            $seller  = (array) $this->appdb->getRowObject('StoreDetails', $product->StoreID);
            $item['seller'] = $seller['Name'];
            $item['distribution'] = profit_distribution($product->Price, $product->CommissionValue, $product->CommissionType);

            $viewData['points'] += $item['distribution']['referral'] * $item['qty'];
            $viewData['shared'] += $item['distribution']['shared_rewards'] * $item['qty'];
            $viewData['cashback'] += $item['distribution']['cashback'] * $item['qty'];
        }

        $viewData['items'] = $cart_items;

        $address = $this->appdb->getRowObjectWhere('UserAddress', array('UserID' => current_user(), 'Status' => 1));

        if ($address) {
            $address->data = lookup_address($address);
        }

        $viewData['address'] = $address;

        // print_data($viewData);

        view('main/marketplace/checkout', $viewData, 'templates/main');
    }

    public function add_to_cart()
    { 
        $product = $this->appdb->getRowObject('StoreItems', get_post('code'), 'Code');
        if ($product) {

            if (get_post('quantity') > 0) {

                $distribution = profit_distribution($product->Price, $product->CommissionValue, $product->CommissionType);

                $data = array(
                    'id'    => $product->id, 
                    'name'  => $product->Name, 
                    'price' => $distribution['discounted_price'], 
                    'qty'   => get_post('quantity'), 
                    'img'   => product_filename($product->Image)
                );

                if ($this->cart->insert($data)) {

                    $seller  = (array) $this->appdb->getRowObject('StoreDetails', $product->StoreID);
                    $p = array(
                        'Image' => product_filename($product->Image),
                        'Name'  => $product->Name,
                        'Price' => peso($distribution['discounted_price']),
                        'Seller'=> $seller['Name']
                    );

                    $return_data = array(
                        'status'     => true,
                        'message'    => 'Item has been added to cart',
                        'item_count' => $this->cart->total_items(),
                        'data'       => $p
                    );

                } else {
                    $return_data = array(
                        'status'    => false,
                        'message'   => 'Failed to add on cart',
                        'data'      => $data
                    );
                }

            } else {
                $return_data = array(
                    'status'    => false,
                    'message'   => 'Invalid quantity',
                );
            }
        } else {
            $return_data = array(
                'status'    => false,
                'message'   => 'Invalid product'
            );
        }

        response_json($return_data);
    }

    public function update_cart_item()
    {

        $data = array(
            'rowid' => get_post('rowid'),
            'qty'   => get_post('quantity')
        );

        $item = $this->cart->get_item(get_post('rowid'));

        if (get_post('quantity') > 0) {

            if ($item) {

                $this->cart->update($data);

                $return_data = array(
                        'status'    => true,
                        'message'   => 'Item has been updated',
                        'item_count'=> $this->cart->total_items(),
                        'subtotal'  => peso($item['subtotal']),
                        'total'     => peso($this->cart->total()),
                    );

            } else {
                $return_data = array(
                    'status'    => false,
                    'message'   => 'Invalid product'
                );
            }

        } else {
            $return_data = array(
                'status'    => false,
                'message'   => 'Invalid quantity',
                'qty'       => ($item['qty'] ?? 0)
            );
        }

        response_json($return_data);
    }

    public function remove_cart_item()
    {
        if ($this->cart->remove(get_post('rowid'))) {
            $return_data = array(
                    'status'    => true,
                    'message'   => 'Invalid product',
                    'item_count'=> $this->cart->total_items(),
                    'total'     => peso($this->cart->total()),
                );
        } else {
            $return_data = array(
                'status'    => false,
                'message'   => 'Cannot remove item on cart.'
            );
        }

        response_json($return_data);
    }


    public function place_order()
    {
        check_authentication();
        if (is_ajax()) {

            $cart_items = $this->cart->contents();

            if ($this->cart->total_items()) {
                $distribution = array(
                    'srp'            => 0,
                    'discount'       => 0,
                    'profit'         => 0,
                    'company'        => 0,
                    'investor'       => 0,
                    'referral'       => 0,
                    'delivery'       => 0,
                    'cashback'       => 0,
                    'shared_rewards' => 0,
                    'divided_reward' => 0
                );
                foreach ($cart_items as &$item) {
                    $product = $this->appdb->getRowObject('StoreItems', $item['id']);
                    $item['distribution'] = profit_distribution($product->Price, $product->CommissionValue, $product->CommissionType);

                    $distribution['srp']            += $item['distribution']['srp'] *= $item['qty'];
                    $distribution['discount']       += $item['distribution']['discount'] *= $item['qty'];
                    $distribution['profit']         += $item['distribution']['profit'] *= $item['qty'];
                    $distribution['company']        += $item['distribution']['company'] *= $item['qty'];
                    $distribution['investor']       += $item['distribution']['investor'] *= $item['qty'];
                    $distribution['referral']       += $item['distribution']['referral'] *= $item['qty'];
                    $distribution['delivery']       += $item['distribution']['delivery'] *= $item['qty'];
                    $distribution['cashback']       += $item['distribution']['cashback'] *= $item['qty'];
                    $distribution['shared_rewards'] += $item['distribution']['shared_rewards'] *= $item['qty'];
                    $distribution['divided_reward'] += $item['distribution']['divided_reward'] *= $item['qty'];
                }

                // print_data($distribution);
                // print_data($cart_items);

                $address = $this->appdb->getRowObjectWhere('UserAddress', array('UserID' => current_user(), 'Status' => 1));

                if ($address) {


                    $latest_balance = get_latest_wallet_balance(current_user());
                    $order_amount   = $this->cart->total();

                    if ($order_amount > 0) {

                        if ($latest_balance >= $order_amount) {

                            $orderData = array(
                                'Code'          => microsecID(true),
                                'OrderBy'       => current_user(),
                                'AddressID'     => $address->id,
                                'PaymentMethod' => 1, // test, default ewallet
                                'DeliveryMethod'=> 2, // test, default to ambilis delivery
                                'ItemCount'     => $this->cart->total_items(),
                                'TotalAmount'   => $order_amount,
                                'Status'        => 4,
                                'Distribution'  => json_encode($distribution),
                                'DateOrdered'   => datetime(),
                                'LastUpdate'    => datetime(),
                            );

                            $this->db->trans_start();

                            if (($ID = $this->appdb->saveData('Orders', $orderData))) {

                                $has_error = false;

                                $cart_items = array_values($cart_items);

                                // add order items
                                foreach ($cart_items as $k => $i) {
                                    $orderItemData = array(
                                        'OrderID'       => $ID,
                                        'ItemID'        => $i['id'],
                                        'ItemName'      => $i['name'],
                                        'Price'         => $i['price'],
                                        'Quantity'      => $i['qty'],
                                        'Distribution'  => json_encode($i['distribution'])
                                    );

                                    if (!$this->appdb->saveData('OrderItems', $orderItemData)) {
                                        $has_error = true;
                                        break;
                                    }
                                }

                                if ($has_error) {
                                    $return_data = array(
                                        'status'    => false,
                                        'message'   => 'Saving order item failed. Please try again later.'
                                    );
                                } else {

                                    $transactionData = array(
                                        'Code'          => microsecID(),
                                        'AccountID'     => current_user(),
                                        'ReferenceNo'   => $orderData['Code'],
                                        'Description'   => 'Made a purchase - Order #' . $orderData['Code'],
                                        'Date'          => date('Y-m-d H:i:s'),
                                        'Amount'        => $order_amount,
                                        'Type'          => 'Debit',
                                        'EndingBalance' => ($latest_balance - $order_amount)
                                    );

                                    if ($this->appdb->saveData('WalletTransactions', $transactionData)) {
                                        

                                        if ($this->distribute_rewards($ID)) {
                                            $return_data = array(
                                                'status'    => true,
                                                'message'   => 'Order has been placed successfully.',
                                                'id'        => $orderData['Code']
                                            );

                                            // clean the cart
                                            $this->cart->destroy();
                                        } else {
                                            $return_data = array(
                                                'status'    => false,
                                                'message'   => 'Order transaction failed.',
                                            );
                                        }

                                    } else {
                                        $return_data = array(
                                            'status'    => false,
                                            'message'   => 'Order transaction failed.',
                                        );
                                    }
                                    
                                }

                            } else {
                                $return_data = array(
                                    'status'    => false,
                                    'message'   => 'Saving order failed. Please try again later.'
                                );
                            }

                            $this->db->trans_complete();

                        } else {
                            $return_data = array(
                                'status'    => false,
                                'message'   => 'Insufficient wallet balance.'
                            );
                        }

                    } else {
                        $return_data = array(
                            'status'    => false,
                            'message'   => 'Invalid order.'
                        );
                    }

                } else {
                    $return_data = array(
                        'status'    => false,
                        'message'   => 'Shipping address is not set.'
                    );
                }
            } else {
                $return_data = array(
                    'status'    => false,
                    'message'   => 'No item to order.'
                );
            }

            response_json($return_data);

        } else {
            redirect();
        }
    }

    /*
    * auto complete order for test, to show earnings
    */
    private function distribute_rewards($order_id)
    {   
        $has_error = false;
        $orderData = $this->appdb->getRowObject('Orders', $order_id);
        if ($orderData) {

            // buyer rewards (cashback, 1/8 shared), log discounts on transactions and rewards but not credited on wallet
            // direct referrer reward (referral points, 1/8 shared)
            // 6 upline of direct referrer (1/8 shared each)

            $buyerInfo = $this->appdb->getRowObject('Users', $orderData->OrderBy);

            $distribution = json_decode($orderData->Distribution);

            // BUYER
            $rewards = array(
                array(
                    'reward_type'   => 'discount',
                    'account_id'    => $orderData->OrderBy,
                    'order_id'      => $orderData->id,
                    'from_user'     => null,
                    'amount'        => $distribution->discount,
                    'trans_desc'    => 'Discounted from order - Order #' . $orderData->Code,
                ),
                array(
                    'reward_type'   => 'cashback',
                    'account_id'    => $orderData->OrderBy,
                    'order_id'      => $orderData->id,
                    'from_user'     => null,
                    'amount'        => $distribution->cashback,
                    'trans_desc'    => 'Cashback from order - Order #' . $orderData->Code,
                ),
                array(
                    'reward_type'   => 'shared',
                    'account_id'    => $orderData->OrderBy,
                    'order_id'      => $orderData->id,
                    'from_user'     => null,
                    'amount'        => $distribution->divided_reward,
                    'trans_desc'    => 'Shared reward from order - Order #' . $orderData->Code,
                )

            );

            // REFERRER
            if ($buyerInfo->Referrer) {
                $referrerData = $this->appdb->getRowObject('Users', $buyerInfo->Referrer);
                if ($referrerData) {
                    $rewards[] = array(
                        'reward_type'   => 'referrer',
                        'account_id'    => $buyerInfo->Referrer,
                        'from_user'     => $orderData->OrderBy,
                        'order_id'      => $orderData->id,
                        'amount'        => $distribution->referral,
                        'trans_desc'    => 'Referral points from Order #' . $orderData->Code . ' by ' . strtoupper($buyerInfo->Firstname . ' ' . $buyerInfo->Lastname),
                    );
                    $rewards[] = array(
                        'reward_type'   => 'shared',
                        'account_id'    => $buyerInfo->Referrer,
                        'from_user'     => $orderData->OrderBy,
                        'order_id'      => $orderData->id,
                        'amount'        => $distribution->divided_reward,
                        'trans_desc'    => 'Shared reward from Order #' . $orderData->Code . ' by ' . strtoupper($buyerInfo->Firstname . ' ' . $buyerInfo->Lastname),
                    );

                    // UPPER REFFERS
                    $upper_referrers = get_upper_referrers($orderData->OrderBy);
                    foreach ($upper_referrers as $user_id) {
                        if (!in_array($user_id, array($orderData->OrderBy, $buyerInfo->Referrer))) {
                            $rewards[] = array(
                                'reward_type'   => 'shared',
                                'account_id'    => $user_id,
                                'order_id'      => $orderData->id,
                                'from_user'     => $orderData->OrderBy,
                                'amount'        => $distribution->divided_reward,
                                'trans_desc'    => 'Shared reward from Order #' . $orderData->Code . ' by ' . strtoupper($buyerInfo->Firstname . ' ' . $buyerInfo->Lastname),
                            );
                        }
                    }
                }
            }

            foreach ($rewards as $data) {

                $reward_type = $data['reward_type'];
                $rewardData = array(
                    'Code'        => microsecID(true),
                    'AccountID'   => $data['account_id'],
                    'FromUserID'  => $data['from_user'],
                    'OrderID'     => $data['order_id'],
                    'Type'        => $reward_type,
                    'Amount'      => $data['amount'],
                    'Description' => $data['trans_desc'],
                    'DateAdded'   => datetime()
                );
                if ($this->appdb->saveData('WalletRewards', $rewardData)) {

                    // if discount type. no changes on wallet, just record the on reward logs
                    if ($reward_type != 'discount') {
                        $balance = get_latest_wallet_balance($data['account_id']);

                        $transactions_data = array(
                            'Code'          => microsecID(true),
                            'AccountID'     => $data['account_id'],
                            'ReferenceNo'   => $rewardData['Code'],
                            'Description'   => $data['trans_desc'],
                            'Date'          => datetime(),
                            'Amount'        => $data['amount'],
                            'Type'          => 'Credit',
                            'EndingBalance' => ($balance + $data['amount'])
                        );

                        if (!$this->appdb->saveData('WalletTransactions', $transactions_data)) {
                            $has_error = true;
                            logger('Saving ' . $reward_type . ' reward transaction failed.');
                        }
                    }
                } else {
                    $has_error = true;
                    logger('Saving ' . $reward_type . ' reward failed.');
                }

                if ($has_error) {
                    break;
                }

            }


        } else{
            $has_error = true;
            logger('Order data not found.');
        }

        return !$has_error;
    }

}
