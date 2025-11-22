<?php
/**
 * Helpers de estilos por campaña.
 */
function drak_campaign_hex_to_rgba( $hex, $alpha = 1 ) {
    $hex = isset( $hex ) ? ltrim( (string) $hex, '#' ) : '';
    if ( strlen( $hex ) === 3 ) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if ( strlen( $hex ) !== 6 ) {
        return '';
    }
    $int = hexdec( $hex );
    $r = ( $int >> 16 ) & 255;
    $g = ( $int >> 8 ) & 255;
    $b = $int & 255;
    return sprintf( 'rgba(%d,%d,%d,%.2f)', $r, $g, $b, max( 0, min( 1, $alpha ) ) );
}
