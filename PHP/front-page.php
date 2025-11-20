<?php
/*
Template Name: Hub de Campañas
*/

get_header();

$campaign_query = new WP_Query( [
    'post_type'      => 'campaign',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
] );

$status_labels = [
    'active'   => 'En curso',
    'paused'   => 'En pausa',
    'finished' => 'Terminada',
];
?>

<style>
.front-campaign-hub {
  --bg: #0d0818;
  --card-bg: #181024;
  --text: #f5f3ff;
  --muted: #cbc4e0;
  --accent: #9b5cff;
  background: radial-gradient(circle at 20% 20%, #1a1030, #0b0615 45%), var(--bg);
  color: var(--text);
  min-height: 100vh;
  padding: 64px 24px 96px;
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
  border: 1px solid #261a3a;
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
  background: linear-gradient(180deg, rgba(0,0,0,0.15) 0%, rgba(0,0,0,0.35) 100%);
}
.campaign-card__body {
  position: relative;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.campaign-card__status {
  display: inline-flex;
  align-self: flex-start;
  padding: 4px 10px;
  font-size: 12px;
  border-radius: 999px;
  background: rgba(155, 92, 255, 0.12);
  color: var(--text);
  border: 1px solid rgba(155, 92, 255, 0.3);
}
.campaign-card__title {
  margin: 0;
  font-size: 20px;
  line-height: 1.2;
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
}
</style>

<main class="front-campaign-hub">
  <div class="front-campaign-hub__header">
    <h1 class="front-campaign-hub__title">ROLASSSSO.</h1>
  </div>

  <?php if ( $campaign_query->have_posts() ) : ?>
    <div class="campaign-grid">
      <?php
      while ( $campaign_query->have_posts() ) :
          $campaign_query->the_post();
          $campaign_id   = get_the_ID();
          $cover_id      = get_field( 'campaign_cover_image', $campaign_id );
          $cover_url     = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '';
          if ( ! $cover_url ) {
              $cover_url = get_the_post_thumbnail_url( $campaign_id, 'large' );
          }
          $status_key    = get_field( 'campaign_status', $campaign_id );
          $status_label  = $status_key ? ( $status_labels[ $status_key ] ?? $status_key ) : '';
          $summary       = get_field( 'campaign_summary', $campaign_id );
          $card_style    = $cover_url ? ' style="background-image: url(' . esc_url( $cover_url ) . ');"' : '';
          ?>
          <article <?php post_class( 'campaign-card' ); ?>>
            <a class="campaign-card__link" href="<?php the_permalink(); ?>">
              <div class="campaign-card__media"<?php echo $card_style; ?>></div>
              <div class="campaign-card__body">
                <?php if ( $status_label ) : ?>
                  <span class="campaign-card__status"><?php echo esc_html( $status_label ); ?></span>
                <?php endif; ?>
                <h2 class="campaign-card__title"><?php the_title(); ?></h2>
                <?php if ( $summary ) : ?>
                  <p class="campaign-card__summary"><?php echo esc_html( $summary ); ?></p>
                <?php endif; ?>
                <span class="campaign-card__cta">Entrar a la campaña →</span>
              </div>
            </a>
          </article>
      <?php endwhile; ?>
    </div>
    <?php wp_reset_postdata(); ?>
  <?php else : ?>
    <p class="front-campaign-hub__empty">Todavía no hay campañas disponibles.</p>
  <?php endif; ?>
</main>

<?php
get_footer();
