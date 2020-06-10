<?php
/*
* Plugin Name: 朝霜
* Plugin URI: https://sateraito.nagoya
* Description: 朝霜ちゃんかわいい
* Version: 0.0.0
* Author: Asahi Himura
* License: GPL2
*/

namespace Asashimo;

add_action('admin_menu', function(){
    $custom_page = add_menu_page(
        "朝霜",
        "朝霜",
        "activate_plugins",
        'asashio',
        'Asashimo\admin_menu'
    );
});

function admin_menu(){
    if(!empty($_POST['edit'])){
        update_option('recaptcha_server_key', $_POST['server_key']);
        update_option('front_url', $_POST['front_url']);
        update_option('name', $_POST['name']);
    }
?>
    <h1>setting</h1>
    <form method="POST">
        <input type="hidden" name="edit" value="yes" />
        <p>
            <label>server key:
                <input type="text" name="server_key" value="<?php echo get_option('recaptcha_server_key'); ?>">
            </label>
        </p>
        <p>
            <label>fron url:
                <input type="url" name="front_url" value="<?php echo get_option('front_url'); ?>">
            </label>
        </p>
        <p>
            <label>name:
                <input type="text" name="name" value="<?php echo get_option('name'); ?>">
            </label>
        </p>
        <input type="submit" />
    </form>
<?php
}

add_action( 'init', function(){
    $labels = [
        'name'          => 'メッセージ',
        'singular_name' => 'メッセージ',
        'edit_item'     => 'edit email',
        'add_new_item'  => 'add new email',
        'new_item'      => 'new email',
    ];

    $args = [
        'labels'              => $labels,
        'public'              => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'has_archive'         => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-email',
        'query_var'           => true,
        'rewrite'             => true,
        'capability_type'     => 'post',
        'has_archive'         => false,
        'can_export'          => true,
        'hierarchical'        => false,
        'taxonomies'          => [],
        'supports'            => ['title', 'editor', 'author'],
        'show_in_rest'        => false,
    ];
    register_post_type( 'message', $args );
});


add_action('admin_init', function(){
    add_meta_box(
        'vws_mail_metabox',
        'mail info',
        'Asashimo\naganami',
        'message',
        'normal',
        'high',
        null
    );
});

add_filter('user_can_richedit', function( $default ){
    if ( get_post_type() === 'message' ) {
        return false;
    }
    return $default;
});

function naganami($post){
    echo "email: " . get_post_meta($post->ID, 'email', true) . "<br/>";
    echo "長波様かわいい: " . get_post_meta($post->ID, 'naganamisama_kawaii', true) . "<br/>";

    if(empty(get_post_meta($post->ID, 'env', true))){return;}
    foreach(get_post_meta($post->ID, 'env', true) as $key => $value){
        echo $key . ": " . $value . "<br/>";
    }
}

add_action( 'rest_api_init', function () {
    $namespace = 'nagoya/v1';
    register_rest_route(
        $namespace,
        '/form',
        array(
            'methods' => 'POST',
            'args' => [
                'name' => [
                    'description' => 'name',
                    'required' => true,
                    'type' => 'string'
                ],
                'email' => [
                    'description' => 'email address',
                    'required' => false,
                    'type' => 'string',
                    'validate_callback' => function($param, $request, $key) { return $param == '' || filter_var($param, FILTER_VALIDATE_EMAIL); }
                ],
                'body' => [
                    'description' => 'body',
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param, $request, $key) { return strlen($param) > 0; }
                ],
                'naganamisama_kawaii' => [
                    'description' => 'is cute naganamisama?',
                    'required' => true,
                    'type' => 'boolean'
                ],
                'token' => [
                    'description' => 'validate token',
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param, $request, $key) { return veryfy_recaptcha( $param, $_SERVER["REMOTE_ADDR"] ); }
                ],
            ],
            'accept_json' => true,
            'callback' => 'Asashimo\api_endpoint',
        )
    );
});

function api_endpoint($data) {
    header("Access-Control-Allow-Origin: *");

    if($data->get_param('debug')) {
        return ['success'=>true, 'dry'=>true];
    }

    $id = wp_insert_post(
        [
            'post_status' => 'publish',
            'post_type' => 'message',
            'post_title' => 'from '. $data->get_param('name'),
            'post_content' => $data->get_param('body'),
        ]
    );

    add_post_meta($id, 'email',  $data->get_param('email'));
    add_post_meta($id, 'naganamisama_kawaii',  $data->get_param('naganamisama_kawaii'));
    add_post_meta($id, 'env',  $_SERVER);

    $attachments = [];
    if ($data->get_param('email')) {
        $attachments[] = "From: ". $data->get_param('name') ." <". $data->get_param('email') .">";
        $attachments[] = "Reply-To: ". $data->get_param('name') ." <". $data->get_param('email') .">";

        $site = get_option('front_url');
        $sitename = get_option('name');

        $name = $data->get_param('email');
        $body = <<<EOL
メールありがとうございます。
大事に読ませて頂きます。

EOL;

        $body .= "\n > ". str_replace("\n", "\n > ", $data->get_param('body'));
        $body .= <<<EOL

------------------------
$sitename $site

EOL;
        wp_mail(
            $data->get_param('email'),
            'Thank You Your Message',
            $body,
            [
                "From: ".get_option('name')." <".get_option('admin_email').">",
            ]
        );
    }

    wp_mail(
        get_option('admin_email'),
        'New Submission from '. $data->get_param('name'),
        $data->get_param('body'),
        $attachments
    );

    return [
        'success' => true
    ];
}

function veryfy_recaptcha( $token, $remoteip=null ){
    $key = get_option('recaptcha_server_key', null);
    if (empty($key)) {
        return true;
    }

    $r = wp_safe_remote_post(
        'https://www.google.com/recaptcha/api/siteverify',
        [
            'body' => [
                'response' => $token,
                'secret' => $key,
                'remoteip' => $remoteip
            ]
        ]
    );
    if (is_wp_error($r)) {
        return null;
    }

    $payload = json_decode($r['body']);
    return $payload->success;
}
