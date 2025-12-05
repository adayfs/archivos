<?php
/**
 * Template Name: Crear Personaje (Wizard)
 */

get_header();

if ( ! function_exists( 'drak_campaign_hex_to_rgba' ) ) {
    function drak_campaign_hex_to_rgba( $hex, $alpha = 1 ) {
        $hex = isset( $hex ) ? ltrim( (string) $hex, '#' ) : '';
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( strlen( $hex ) !== 6 ) {
            return '';
        }
        $int = hexdec( $hex );
        $r   = ( $int >> 16 ) & 255;
        $g   = ( $int >> 8 ) & 255;
        $b   = $int & 255;
        return sprintf( 'rgba(%d,%d,%d,%.2f)', $r, $g, $b, max( 0, min( 1, $alpha ) ) );
    }
}

$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
$campaign_title = $campaign_id ? get_the_title( $campaign_id ) : '';
$campaign_url   = $campaign_id ? get_permalink( $campaign_id ) : '';
$cover_id       = $campaign_id ? get_field( 'campaign_cover_image', $campaign_id ) : 0;
$cover_url      = $cover_id ? wp_get_attachment_image_url( $cover_id, 'full' ) : '';
$color          = $campaign_id ? get_field( 'campaign_color', $campaign_id ) : '';
$accent_dark    = $color ? drak_campaign_hex_to_rgba( $color, 0.25 ) : 'rgba(155, 92, 255, 0.25)';
$hero_style_raw = $color ? '--campaign-accent:' . esc_attr( $color ) . ';--accent:' . esc_attr( $color ) . ';--accent-dark:' . esc_attr( $accent_dark ) . ';' : '--accent:#9b5cff;--accent-dark:rgba(155,92,255,0.25);';
if ( $cover_url ) {
    $hero_style_raw .= 'background-image:url(' . esc_url( $cover_url ) . ');';
}
$hero_style = ' style="' . $hero_style_raw . '"';
$shell_style = $color ? ' style="--accent:' . esc_attr( $color ) . ';--accent-dark:' . esc_attr( $accent_dark ) . ';--wizard-bg:#0d0818;--wizard-card:#181024;--wizard-text:#fdf9ff;"' : '';
$logo_id        = $campaign_id ? drak_get_campaign_logo_id( $campaign_id ) : 0;
$logo_html      = $logo_id ? wp_get_attachment_image( $logo_id, 'medium', false, [ 'class' => 'campaign-hero__logo-img' ] ) : '';

$ajax_url = drak_get_admin_ajax_url();
?>

<style>
.wizard-shell {
  min-height: 100vh;
  background: linear-gradient(180deg, rgba(0,0,0,0.6), var(--wizard-bg, #0d0818));
  color: var(--wizard-text, #fdf9ff);
  padding-bottom: 48px;
}
.page-template-page-crear-personaje .site-content > .ast-container {
  max-width: 100%;
  padding: 0;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.wizard-hero {
  position: relative;
  padding: 120px 32px 80px;
  background-size: cover;
  background-position: center;
  margin: 0 auto 24px;
  border-radius: 12px;
  overflow: hidden;
  max-width: 1200px;
}
.wizard-hero__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0,0,0,0.25), rgba(0,0,0,0.75));
}
.wizard-hero__content {
  position: relative;
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: center;
  text-align: center;
  color: #fff;
  bottom: -82px;
}
.wizard-hero__title {
  margin: 0;
  font-size: clamp(30px, 6vw, 54px);
  color: var(--accent, #9b5cff);
  letter-spacing: -0.02em;
}
.wizard-hero__back {
  position: absolute;
  left: 20px;
  bottom: 20px;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--accent, #9b5cff);
  background: var(--accent-dark, rgba(155, 92, 255, 0.2));
  color: #fff;
  text-decoration: none;
  font-size: 12px;
}
.wizard-hero__logo {
  max-width: 220px;
}
.personaje-wizard {
  max-width: 1100px;
  margin: 0 auto 48px;
  padding: 0 24px;
}
.personaje-wizard__title {
  margin-top: 0;
  text-align: center;
  color: #bfc1c5;
}
.personaje-wizard label {
  color: var(--wizard-text, #fdf9ff);
}
.personaje-wizard input,
.personaje-wizard select,
.personaje-wizard textarea {
  width: 100%;
  background: var(--wizard-card, #181024);
  border: 1px solid var(--accent, #9b5cff);
  color: var(--wizard-text, #fdf9ff);
  border-radius: 8px;
  padding: 10px;
  box-sizing: border-box;
  margin-top: 4px;
}
.personaje-wizard textarea {
  min-height: 120px;
}
.personaje-wizard .pw-btn,
.wizard-hero__back {
  background: var(--accent-dark, rgba(155, 92, 255, 0.2));
  color: var(--wizard-text, #fdf9ff);
  border-color: var(--accent, #9b5cff);
}
.pw-step h3 {
  color: var(--accent, #9b5cff);
}
.pw-fieldset {
  border: 1px solid var(--accent, #9b5cff);
}
.pw-inline {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}
.pw-nav {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 16px;
}
.pw-nav-spacer {
  flex: 1;
}
.pw-image-picker {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  flex-wrap: wrap;
}
.pw-image-preview img {
  max-width: 200px;
  height: auto;
  border-radius: 8px;
  display: block;
}
.pw-step > * {
  margin-bottom: 12px;
}
.pw-fieldset {
  background: var(--wizard-card, #181024);
  padding: 12px;
  border-radius: 8px;
}
.pw-class-features-wrapper {
  margin-top: 16px;
}
.pw-class-features {
  background: var(--wizard-card, #181024);
  border: 1px solid var(--accent, #9b5cff);
  border-radius: 10px;
  padding: 10px;
}
.feature-card {
  border: 1px solid var(--accent, #9b5cff);
  border-radius: 8px;
  margin-bottom: 10px;
  background: rgba(0,0,0,0.15);
}
.feature-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  cursor: pointer;
}
.feature-card__title {
  margin: 0;
  font-size: 16px;
  color: var(--wizard-text, #fdf9ff);
}
.feature-card__toggle {
  background: transparent;
  border: none;
  color: var(--wizard-text, #fdf9ff);
  font-size: 14px;
  cursor: pointer;
}
.feature-card__content {
  padding: 0 12px 12px;
}
.feature-card__meta {
  font-size: 12px;
  color: #b0b3c0;
  margin-bottom: 6px;
}
.feature-card__body p {
  margin: 0 0 6px;
  color: var(--wizard-text, #fdf9ff);
}
@media (max-width: 720px) {
  .wizard-hero {
    padding: 90px 20px 60px;
  }
  .wizard-hero__back {
    bottom: 12px;
    left: 12px;
  }
  .wizard-hero__content {
    align-items: flex-start;
    text-align: left;
  }
  .personaje-wizard {
    padding: 0 16px;
  }
  .pw-nav {
    flex-wrap: wrap;
    justify-content: center;
  }
}
</style>

<div class="wizard-shell"<?php echo $shell_style; ?>>
  <?php if ( $campaign_id ) : ?>
    <section class="wizard-hero"<?php echo $hero_style; ?>>
      <div class="wizard-hero__overlay"></div>
      <div class="wizard-hero__content">
        <?php if ( $campaign_url ) : ?>
          <a class="wizard-hero__back drak-btn" href="<?php echo esc_url( $campaign_url ); ?>">← Volver a la campaña</a>
        <?php endif; ?>
        <?php if ( $logo_html ) : ?>
          <div class="wizard-hero__logo"><?php echo $logo_html; ?></div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <div class="personaje-wizard">
    <h2 class="personaje-wizard__title">Asistente de creación de personaje</h2>
    <div id="personaje-wizard-root" class="personaje-wizard__root" data-ajax="<?php echo esc_url( $ajax_url ); ?>"></div>
  </div>
</div>

<?php
get_footer();
