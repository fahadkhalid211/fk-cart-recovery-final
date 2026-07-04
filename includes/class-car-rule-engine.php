<?php
defined( 'ABSPATH' ) || exit;

class CAR_Rule_Engine {

    /**
     * Check if a campaign should fire for a given cart.
     * Returns true if the campaign SHOULD send, false if blocked by a rule.
     */
    public static function should_send( $campaign, $cart ) {
        $rules = CAR_DB::get_rules( $campaign->id );
        if ( empty( $rules ) ) return true;

        $cart_total = floatval( $cart->cart_total );
        $items      = maybe_unserialize( $cart->cart_contents );
        $product_ids= [];
        $category_ids= [];

        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $product_ids[] = $item['product_id'];
                $cats = wc_get_product_term_ids( $item['product_id'], 'product_cat' );
                $category_ids  = array_merge( $category_ids, $cats );
            }
        }
        $product_ids  = array_unique( $product_ids );
        $category_ids = array_unique( $category_ids );

        foreach ( $rules as $rule ) {
            $matched = self::evaluate_rule( $rule, $cart_total, $product_ids, $category_ids, $cart );

            if ( $matched ) {
                switch ( $rule->action ) {
                    case 'skip':
                        return false;
                    case 'send_only':
                        // If rule matches and action is send_only, allow
                        return true;
                    case 'stop_all':
                        return false;
                }
            } elseif ( $rule->action === 'send_only' ) {
                // send_only but condition NOT met – block
                return false;
            }
        }

        return true;
    }

    private static function evaluate_rule( $rule, $cart_total, $product_ids, $category_ids, $cart ) {
        $value    = $rule->rule_value;
        $operator = $rule->operator;

        switch ( $rule->rule_type ) {

            case 'cart_total':
                return self::compare_numeric( $cart_total, $operator, floatval( $value ) );

            case 'product_in_cart':
                $ids = array_map( 'absint', explode( ',', $value ) );
                return ! empty( array_intersect( $ids, $product_ids ) );

            case 'product_not_in_cart':
                $ids = array_map( 'absint', explode( ',', $value ) );
                return empty( array_intersect( $ids, $product_ids ) );

            case 'category_in_cart':
                $ids = array_map( 'absint', explode( ',', $value ) );
                return ! empty( array_intersect( $ids, $category_ids ) );

            case 'category_not_in_cart':
                $ids = array_map( 'absint', explode( ',', $value ) );
                return empty( array_intersect( $ids, $category_ids ) );

            case 'customer_email_contains':
                return strpos( $cart->email, $value ) !== false;

            case 'is_logged_in':
                return $value === 'yes' ? $cart->user_id > 0 : $cart->user_id == 0;

            case 'gdpr_consent':
                return $value === 'yes' ? (bool) $cart->gdpr_consent : ! (bool) $cart->gdpr_consent;
        }

        return false;
    }

    private static function compare_numeric( $a, $operator, $b ) {
        switch ( $operator ) {
            case 'equals':            return $a == $b;
            case 'not_equals':        return $a != $b;
            case 'greater_than':      return $a > $b;
            case 'greater_than_equal':return $a >= $b;
            case 'less_than':         return $a < $b;
            case 'less_than_equal':   return $a <= $b;
        }
        return false;
    }

    public static function get_rule_types() {
        return [
            'cart_total'           => __( 'Cart Total', 'fk-cart-recovery' ),
            'product_in_cart'      => __( 'Product in Cart (ID)', 'fk-cart-recovery' ),
            'product_not_in_cart'  => __( 'Product NOT in Cart (ID)', 'fk-cart-recovery' ),
            'category_in_cart'     => __( 'Category in Cart (ID)', 'fk-cart-recovery' ),
            'category_not_in_cart' => __( 'Category NOT in Cart (ID)', 'fk-cart-recovery' ),
            'customer_email_contains'=> __( 'Customer Email Contains', 'fk-cart-recovery' ),
            'is_logged_in'         => __( 'Customer Logged In', 'fk-cart-recovery' ),
            'gdpr_consent'         => __( 'GDPR Consent Given', 'fk-cart-recovery' ),
        ];
    }

    public static function get_operators() {
        return [
            'equals'             => __( 'Equals', 'fk-cart-recovery' ),
            'not_equals'         => __( 'Not Equals', 'fk-cart-recovery' ),
            'greater_than'       => __( 'Greater Than', 'fk-cart-recovery' ),
            'greater_than_equal' => __( 'Greater Than or Equal', 'fk-cart-recovery' ),
            'less_than'          => __( 'Less Than', 'fk-cart-recovery' ),
            'less_than_equal'    => __( 'Less Than or Equal', 'fk-cart-recovery' ),
        ];
    }

    public static function get_actions() {
        return [
            'skip'      => __( 'Skip this email', 'fk-cart-recovery' ),
            'send_only' => __( 'Send ONLY if condition is met', 'fk-cart-recovery' ),
            'stop_all'  => __( 'Stop all future emails', 'fk-cart-recovery' ),
        ];
    }
}
