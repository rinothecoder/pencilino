<?php
/**
 * Pencilino admin: About tab.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Admin_About {
	/**
	 * About tab.
	 *
	 * @return void
	 */
	public static function render() {
		?>
		<div class="pencil-content">
			<div class="pencil-card pencil-about">
				<div class="pencil-page-intro">
					<span class="pencil-eyebrow"><?php esc_html_e( 'About', 'pencilino' ); ?></span>
					<h2><?php esc_html_e( 'The story behind Pencilino', 'pencilino' ); ?></h2>
				</div>
				<img src="<?php echo esc_url( PENCIL_PLUGIN_URL . 'admin/img/rino-profile.jpg' ); ?>" alt="Rino de Boer" class="pencil-about__photo" width="80" height="80">

				<p>Hey, my name is Rino. I used Elementor for seven years to build websites for clients. I also taught it on YouTube and in courses. I was a huge fan. But over time, the cracks started to show.</p>

				<p>The editor got slower and heavier. Sometimes it crashed, or I waited minutes for it to load. Updates felt like gambling. Every release made the tool more complicated, and it took me hours to figure out.</p>

				<p>Then AI came and changed the way I work. I stopped using Figma. And I realized that if you build a custom theme, you don't need a pagebuilder either. It's fast, the sites are lighter, and there is no limit to the design. Control over the design is why I started with Elementor in the first place. Now AI gives me that control, and more efficiently.</p>

				<p>But there was one problem. An AI-built website can't be edited. The client has to call me for every single word.</p>

				<p>So I built Pencilino. You build your website with AI, and the content stays editable for the client, right on the page. In the easiest way possible. The plugins you already trust on WordPress just keep working.</p>

				<p>Want a different layout? The client leaves a comment on the exact spot, or tells me, and I change it. They only touch the content. That's all a client should do.</p>

				<p>Let me be honest about what Pencilino is not. It needs an AI like Codex or Claude to build the theme (see Get started), it won't make an existing site editable, and the quality depends on the model you use.</p>

				<p>I don't think we need a pagebuilder anymore, not for small projects and not for big ones. That's a strange thing to say after all those years. But it's where I am right now.</p>

				<p>I hope Pencilino makes handing over an AI-built site a little easier. That's really all it's supposed to do.</p>

				<img src="<?php echo esc_url( PENCIL_PLUGIN_URL . 'admin/img/rino-signature.svg' ); ?>" alt="Rino" class="pencil-about__signature" width="60" height="32" aria-hidden="true">

				<div class="pencil-about__feedback">
					<p><?php esc_html_e( 'Got an idea, or feedback on something that could be better?', 'pencilino' ); ?></p>
					<a href="<?php echo esc_url( PENCIL_FEEDBACK_URL ); ?>" target="_blank" rel="noopener noreferrer" class="pencil-button"><?php esc_html_e( 'Share an idea or feedback', 'pencilino' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}
}
