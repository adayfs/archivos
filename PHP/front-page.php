<?php
/*
Template Name: Hub de Campañas
*/

get_header();

$status_labels = [
    'active'   => 'En curso',
    'paused'   => 'En pausa',
    'finished' => 'Terminada',
];

$status_weight = [
    'active'   => 1,
    'paused'   => 2,
    'finished' => 3,
];

$campaign_query = new WP_Query( [
    'post_type'      => 'campaign',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
] );

$campaigns = $campaign_query->posts;

function drak_front_hex_to_rgba( $hex, $alpha = 1 ) {
    $hex = ltrim( $hex, '#' );
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

function drak_front_get_dark_overlay( $hex, $alpha = 0.25 ) {
    $rgba = drak_front_hex_to_rgba( $hex, $alpha );
    return $rgba ? $rgba : 'rgba(0,0,0,0.35)';
}

usort( $campaigns, function ( $a, $b ) use ( $status_weight ) {
    $sa = get_field( 'campaign_status', $a->ID ) ?: 'finished';
    $sb = get_field( 'campaign_status', $b->ID ) ?: 'finished';
    $wa = $status_weight[ $sa ] ?? 4;
    $wb = $status_weight[ $sb ] ?? 4;
    if ( $wa === $wb ) {
        return strcmp( $a->post_title, $b->post_title );
    }
    return $wa - $wb;
} );
?>

<style>
.front-campaign-hub {
  --bg: #000;
  --card-bg: #0f0a17;
  --text: #f5f3ff;
  --muted: #cbc4e0;
  --accent: #9b5cff;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  padding: 0 24px 96px;
}
.full-width-home {
  max-width: 100%;
}
.ast-container.full-width-home {
  display: block;
}
.home .site-content .ast-container {
  width: 100%;
  max-width: 100%;
  display: block;
}
.home #masthead,
.home .ast-mobile-header-wrap,
.home .main-header-bar,
.home .site-header {
  display: none;
}
.front-campaign-hero {
  width: calc(100% + 48px);
  margin: 0 -24px 40px;
}
.front-campaign-hero img {
  display: block;
  width: 100%;
  height: auto;
  object-fit: contain;
}
.front-campaign-titleband {
  width: 100%;
  margin: 0 0 32px;
}
.front-campaign-titleband__inner {
  text-align: center;
  color: #f5f5f5;
  padding: 16px 0;
}
.front-campaign-titleband__title {
  margin: 0;
  font-size: clamp(38px, 5vw, 64px);
  letter-spacing: 0.08em;
  background: linear-gradient(90deg, #b0b0b0 0%, #000000 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.front-campaign-titleband__divider {
  margin: 12px auto 0;
  border: 0;
  height: 2px;
  width: min(420px, 80%);
  background: linear-gradient(90deg, rgba(255,255,255,0.6), rgba(255,255,255,0));
}
.front-campaign-hub__title {
  font-size: clamp(48px, 6vw, 80px);
  letter-spacing: -0.02em;
  text-transform: uppercase;
  margin: 0 0 32px;
  color: var(--text);
}
.campaign-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 24px;
}
.campaign-card {
  background: var(--card-bg);
  border: 1px solid var(--card-accent, #261a3a);
  border-radius: 16px;
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.campaign-card__link {
  display: flex;
  flex-direction: column;
  text-decoration: none;
  color: inherit;
  height: 100%;
}
.campaign-card__media {
  position: relative;
  width: 100%;
  padding-top: 60%;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  background-color: #241632;
}
.campaign-card__media::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0,0,0,0.15) 0%, var(--card-accent-rgba, rgba(0,0,0,0.45)) 100%);
}
.campaign-card__body {
  position: relative;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: linear-gradient(180deg, var(--card-accent-dark, rgba(0,0,0,0.4)), rgba(0,0,0,0.8));
  min-height: 140px;
}
.campaign-card__status {
  position: absolute;
  bottom: 10px;
  right: 10px;
  display: inline-flex;
  padding: 3px 8px;
  font-size: 11px;
  border-radius: 999px;
  background: var(--card-accent, rgba(155, 92, 255, 0.6));
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(2px);
}
.campaign-card__title {
  margin: 0;
  font-size: 20px;
  line-height: 1.2;
  text-align: center;
  color: var(--card-accent, #f5f3ff);
}
.campaign-card__summary {
  margin: 0;
  color: var(--muted);
  font-size: 14px;
  line-height: 1.4;
}
.campaign-card__cta {
  margin-top: auto;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: var(--accent);
}
.campaign-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
  border-color: rgba(155, 92, 255, 0.3);
}
.front-campaign-hub__empty {
  color: var(--muted);
  font-size: 16px;
}
@media (max-width: 640px) {
  .front-campaign-hub {
    padding: 48px 16px 72px;
  }
  .front-campaign-hero {
    width: calc(100% + 32px);
    margin: 0 -16px 32px;
  }
}
</style>

<main class="front-campaign-hub full-width-home">
  <section class="front-campaign-hero">
    <img src="https://adayfs.com/wp-content/uploads/2025/11/cabeceraDM.png" alt="Cabecera de campaña">
  </section>

  <section class="front-campaign-titleband">
    <div class="front-campaign-titleband__inner">
      <h1 class="front-campaign-titleband__title">ROLASSSSO</h1>
      <hr class="front-campaign-titleband__divider">
    </div>
  </section>

  <?php if ( ! empty( $campaigns ) ) : ?>
    <div class="campaign-grid">
      <?php
      foreach ( $campaigns as $campaign_post ) :
          $campaign_id   = $campaign_post->ID;
          setup_postdata( $campaign_post );
          $cover_id      = get_field( 'campaign_cover_image', $campaign_id );
          $cover_url     = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '';
          if ( ! $cover_url ) {
              $cover_url = get_the_post_thumbnail_url( $campaign_id, 'large' );
          }
          $status_key    = get_field( 'campaign_status', $campaign_id );
        $status_label  = $status_key ? ( $status_labels[ $status_key ] ?? $status_key ) : '';
        $summary       = get_field( 'campaign_summary', $campaign_id );
        $color         = get_field( 'campaign_color', $campaign_id );
        $card_style    = $cover_url ? ' style="background-image: url(' . esc_url( $cover_url ) . ');"' : '';
        $accent_rgba   = $color ? drak_front_hex_to_rgba( $color, 0.45 ) : '';
        $accent_dark   = $color ? drak_front_get_dark_overlay( $color, 0.35 ) : '';
        $card_vars     = $color ? ' style="--card-accent:' . esc_attr( $color ) . ';--card-accent-rgba:' . esc_attr( $accent_rgba ) . ';--card-accent-dark:' . esc_attr( $accent_dark ) . ';"' : '';
        $body_style    = '';
        $campaign_link = get_permalink( $campaign_id );
        ?>
          <article <?php post_class( 'campaign-card', $campaign_post ); ?><?php echo $card_vars; ?>>
            <a class="campaign-card__link" href="<?php echo esc_url( $campaign_link ); ?>">
              <div class="campaign-card__media"<?php echo $card_style; ?>></div>
              <div class="campaign-card__body">
                <?php if ( $status_label ) : ?>
                  <span class="campaign-card__status"><?php echo esc_html( $status_label ); ?></span>
                <?php endif; ?>
                <h2 class="campaign-card__title"><?php echo esc_html( get_the_title( $campaign_id ) ); ?></h2>
                <?php if ( $summary ) : ?>
                  <p class="campaign-card__summary"><?php echo esc_html( $summary ); ?></p>
                <?php endif; ?>
              </div>
            </a>
          </article>
      <?php endforeach; wp_reset_postdata(); ?>
    </div>
  <?php else : ?>
    <p class="front-campaign-hub__empty">Todavía no hay campañas disponibles.</p>
  <?php endif; ?>
</main>

<?php
get_footer();
