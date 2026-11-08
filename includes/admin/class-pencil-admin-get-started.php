<?php
/**
 * Pencilino admin: Get started tab: what Pencilino does, for you and for your clients.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Admin_Get_Started {
	/**
	 * Small website picture used by the For clients walkthrough.
	 *
	 * @param bool $edit Whether the headline shows the purple edit outline and pencil cursor.
	 * @return void
	 */
	private static function render_guide_site( $edit ) {
		?>
		<div class="pencil-gsite">
			<span class="pencil-gsite__nav"><strong><?php esc_html_e( 'Marble & Oak', 'pencilino' ); ?></strong><span><?php esc_html_e( 'Shop', 'pencilino' ); ?></span><span><?php esc_html_e( 'Story', 'pencilino' ); ?></span></span>
			<span class="pencil-gsite__hero">
				<span class="pencil-gsite__h<?php echo $edit ? ' pencil-gsite__edit' : ''; ?>"><?php esc_html_e( 'Freshly roasted coffee', 'pencilino' ); ?></span><?php if ( $edit ) : ?><img class="pencil-gsite__cursor" src="<?php echo esc_url( PENCIL_PLUGIN_URL . 'assets/img/cursor.svg' ); ?>" alt="" width="24" height="24"><?php endif; ?>
				<span class="pencil-gsite__p"><?php esc_html_e( 'Small-batch beans, shipped within two working days.', 'pencilino' ); ?></span>
				<span class="pencil-gsite__btn"><?php esc_html_e( 'Shop beans', 'pencilino' ); ?></span>
			</span>
			<?php if ( $edit ) : ?>
			<span class="pencil-gtool"><span class="pencil-gtool__btn"><?php esc_html_e( 'Edit', 'pencilino' ); ?><span class="dashicons dashicons-edit"></span></span></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get started tab: for people, you and your clients. The instructions for AI agents have their own tab.
	 *
	 * @return void
	 */
	public static function render() {
		$site_line         = sprintf( /* translators: %s: the address of this website */ __( 'Website: %s. Work on it through your MCP connection to this website. If you are not connected to it, stop and tell me.', 'pencilino' ), home_url( '/' ) );
		$prompt_intro      = __( 'We are going to build a custom WordPress theme that works with the Pencilino plugin. Pencilino has specific theme-building instructions that you must follow throughout the project. Read the complete instructions first with the pencilino/get-instructions ability. It returns all instructions, including any that the site owner added, and those additions win when they conflict. If you cannot reach the ability, stop and tell me.', 'pencilino' );
		$idea_prompt       = implode(
			"\n\n",
			array(
				$site_line,
				$prompt_intro,
				__( 'Generate the website design and code inside the theme according to the prompt I provide. Build the first complete version and follow the Pencilino instructions throughout the project.', 'pencilino' ),
				__( 'Website idea or design brief:', 'pencilino' ),
			)
		);
		$html_prompt       = implode(
			"\n\n",
			array(
				$site_line,
				$prompt_intro,
				__( 'I am providing an existing HTML design. That can be a single HTML file, an HTML prototype with separate CSS, JavaScript, fonts and images, or a whole folder of files. Use everything I provide and convert it into a maintainable custom WordPress theme by following the Pencilino instructions.', 'pencilino' ),
				__( 'HTML files or folder location:', 'pencilino' ),
			)
		);
		?>
		<div class="pencil-content pencil-content--onboarding">
			<div class="pencil-card pencil-card--full">
				<div data-subtabs>
					<div class="pencil-subtabs" role="tablist">
						<button type="button" class="pencil-subtabs__btn pencil-subtabs__btn--active" data-subtab="humans" role="tab" aria-selected="true"><?php esc_html_e( 'For you', 'pencilino' ); ?></button>
						<button type="button" class="pencil-subtabs__btn" data-subtab="clients" role="tab" aria-selected="false"><?php esc_html_e( 'For clients', 'pencilino' ); ?></button>
					</div>

					<div data-subtab-panel="humans">
						<div class="pencil-get-started__intro">
							<span class="pencil-eyebrow"><?php esc_html_e( 'How to use', 'pencilino' ); ?></span>
							<h2><?php esc_html_e( 'Build a custom theme with your AI coding tool', 'pencilino' ); ?></h2>
							<p><?php esc_html_e( 'Pencilino provides the editable content layer and the rules your agent follows. Your coding tool builds the WordPress theme.', 'pencilino' ); ?></p>
						</div>

						<h3 class="pencil-section-heading"><?php esc_html_e( 'Phase 1: Connect your AI tool through MCP', 'pencilino' ); ?></h3>
						<p><?php esc_html_e( 'Pick an MCP plugin or solution you like, add it to this website and connect it to your favourite AI tool.', 'pencilino' ); ?></p>
						<div class="pencil-agent-tools" aria-label="<?php esc_attr_e( 'Compatible AI coding tools', 'pencilino' ); ?>">
							<span class="pencil-agent-tools__label"><?php esc_html_e( 'Works with', 'pencilino' ); ?></span>
							<span class="pencil-agent-tool"><?php esc_html_e( 'Codex', 'pencilino' ); ?></span>
							<span class="pencil-agent-tool"><?php esc_html_e( 'Claude', 'pencilino' ); ?></span>
							<span class="pencil-agent-tool"><?php esc_html_e( 'Cursor', 'pencilino' ); ?></span>
							<span class="pencil-agent-tool"><?php esc_html_e( 'Gemini', 'pencilino' ); ?></span>
							<span class="pencil-agent-tool"><?php esc_html_e( 'Any other coding agents', 'pencilino' ); ?></span>
						</div>

						<h3 class="pencil-section-heading"><?php esc_html_e( 'Phase 2: Start something new', 'pencilino' ); ?></h3>
						<p><?php esc_html_e( 'Copy the prompt for your starting point, add your idea or the location of your HTML files, and let your agent build the theme. The link to this website is already in it.', 'pencilino' ); ?></p>

						<div class="pencil-paths">
							<article class="pencil-path">
								<div class="pencil-path__icon"><span class="dashicons dashicons-art" aria-hidden="true"></span></div>
								<span class="pencil-path__number"><?php esc_html_e( 'Path 1', 'pencilino' ); ?></span>
								<h3><?php esc_html_e( 'Start from an idea', 'pencilino' ); ?></h3>
								<p><?php esc_html_e( 'Describe the website and let your agent design and build the complete theme from scratch.', 'pencilino' ); ?></p>
								<button type="button" class="pencil-button" data-copy="pencil-idea-prompt" data-copied-label="<?php esc_attr_e( 'Prompt copied', 'pencilino' ); ?>">
									<?php esc_html_e( 'Copy starter prompt', 'pencilino' ); ?>
								</button>
								<textarea id="pencil-idea-prompt" class="pencil-copy-source" readonly tabindex="-1" aria-hidden="true"><?php echo esc_textarea( $idea_prompt ); ?></textarea>
							</article>

							<article class="pencil-path">
								<div class="pencil-path__icon"><span class="dashicons dashicons-media-code" aria-hidden="true"></span></div>
								<span class="pencil-path__number"><?php esc_html_e( 'Path 2', 'pencilino' ); ?></span>
								<h3><?php esc_html_e( 'Convert an HTML prototype', 'pencilino' ); ?></h3>
								<p><?php esc_html_e( 'Give your agent your HTML design, from a single file to a whole folder with CSS, scripts and images, and have it keep the design while turning it into a proper WordPress theme.', 'pencilino' ); ?></p>
								<button type="button" class="pencil-button" data-copy="pencil-html-prompt" data-copied-label="<?php esc_attr_e( 'Prompt copied', 'pencilino' ); ?>">
									<?php esc_html_e( 'Copy conversion prompt', 'pencilino' ); ?>
								</button>
								<textarea id="pencil-html-prompt" class="pencil-copy-source" readonly tabindex="-1" aria-hidden="true"><?php echo esc_textarea( $html_prompt ); ?></textarea>
							</article>
						</div>

						<h3 class="pencil-section-heading"><?php esc_html_e( 'Phase 3: Build the website', 'pencilino' ); ?></h3>
						<p><?php esc_html_e( 'Keep prompting and build the website the way you want. Now and then, remind your agent to follow the Pencilino instructions. It is not required: Phase 4 checks the whole site afterwards.', 'pencilino' ); ?></p>

						<h3 class="pencil-section-heading"><?php esc_html_e( 'Phase 4: Clean up before you launch', 'pencilino' ); ?></h3>
						<p><?php esc_html_e( 'AI makes mistakes. It skips a step, jumps ahead and forgets things along the way. The clean-up prompt makes your agent check the whole site against the Pencilino instructions, from the CSS to SEO, mobile and speed, and fix what slipped. It ends with a short summary in plain words. After that, check every page yourself and test your text, buttons and images in Pencilino.', 'pencilino' ); ?></p>
						<button type="button" class="pencil-button" data-copy="pencil-cleanup-prompt-howto" data-copied-label="<?php esc_attr_e( 'Prompt copied', 'pencilino' ); ?>"><?php esc_html_e( 'Copy clean-up prompt', 'pencilino' ); ?></button>
						<textarea id="pencil-cleanup-prompt-howto" class="pencil-copy-source" readonly tabindex="-1" aria-hidden="true"><?php echo esc_textarea( Pencil_Dev::master_prompt() ); ?></textarea>

						<div class="pencil-get-started__intro pencil-good">
							<span class="pencil-eyebrow"><?php esc_html_e( 'Good to know', 'pencilino' ); ?></span>
							<h2><?php esc_html_e( 'Light for visitors, safe to build on', 'pencilino' ); ?></h2>
						</div>
						<ul class="pencil-good__list">
							<li><span class="pencil-good__check dashicons dashicons-yes" aria-hidden="true"></span><div><h3><?php esc_html_e( 'Your website stays fast', 'pencilino' ); ?></h3><p><?php esc_html_e( 'Pencilino only loads for people who are logged in and allowed to edit. Your visitors never load the Edit button or the editing tools, so your website stays just as fast.', 'pencilino' ); ?></p></div></li>
							<li><span class="pencil-good__check dashicons dashicons-yes" aria-hidden="true"></span><div><h3><?php esc_html_e( 'Turning Pencilino off is safe', 'pencilino' ); ?></h3><p><?php esc_html_e( 'Your agent builds a normal WordPress theme, so every page keeps showing its content without Pencilino. We do recommend keeping it on: without it there is no easy editing and no AI instructions to keep the theme well built.', 'pencilino' ); ?></p></div></li>
							<li><span class="pencil-good__check dashicons dashicons-yes" aria-hidden="true"></span><div><h3><?php esc_html_e( 'A theme you can hand over', 'pencilino' ); ?></h3><p><?php esc_html_e( 'Everything is built the same way: one design system, real WordPress pages and images in the Media Library. Another developer with their own AI agent can pick it up and keep building without starting over.', 'pencilino' ); ?></p></div></li>
						</ul>
					</div>

					<div data-subtab-panel="clients" hidden>
						<div class="pencil-get-started__intro pencil-guide__intro">
							<span class="pencil-eyebrow"><?php esc_html_e( 'The problem', 'pencilino' ); ?></span>
							<h2><?php esc_html_e( 'Clients want simple page editing', 'pencilino' ); ?></h2>
							<p class="pencil-guide__problem"><?php esc_html_e( 'Clients are often overwhelmed when they log in to the WordPress dashboard. Pencilino changes that with a super easy front-end editing experience, so they only go to the dashboard when they really need to. It is the easiest way to let clients make changes to an AI-generated WordPress theme: click the pencil, edit, save. It also works with dynamic plugins through a popup, and you as the developer keep full control over how the theme is built.', 'pencilino' ); ?></p>
						</div>

						<div class="pencil-guide__steps">
							<div class="pencil-guide__row">
								<div><span class="pencil-guide__n">1</span><h3><?php esc_html_e( 'They log in and land on the website', 'pencilino' ); ?></h3><p><?php if ( Pencil_Client::can_manage() ) { /* translators: %s: link to the Focus mode settings */ echo wp_kses( sprintf( __( '%s starts them on the website, with a simple top bar instead of the full WordPress menu.', 'pencilino' ), '<a href="#settings-focus">' . esc_html__( 'Focus mode', 'pencilino' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) ); } else { esc_html_e( 'Focus mode starts them on the website, with a simple top bar instead of the full WordPress menu.', 'pencilino' ); } ?></p></div>
								<div class="pencil-guide__vis" aria-hidden="true">
									<div class="pencil-glogin"><span class="pencil-glogin__t"><?php esc_html_e( 'Log in', 'pencilino' ); ?></span><span class="pencil-glogin__f"><?php esc_html_e( 'name@company.com', 'pencilino' ); ?></span><span class="pencil-glogin__f">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span><span class="pencil-glogin__b"><?php esc_html_e( 'Log in', 'pencilino' ); ?></span></div>
									<span class="dashicons dashicons-arrow-right-alt pencil-guide__arrow"></span>
									<?php self::render_guide_site( false ); ?>
								</div>
							</div>

							<div class="pencil-guide__row">
								<div><span class="pencil-guide__n">2</span><h3><?php esc_html_e( 'They point at text and click', 'pencilino' ); ?></h3><p><?php esc_html_e( 'Anything they can change gets a purple outline and a pencil cursor. A click opens it in a popup.', 'pencilino' ); ?></p></div>
								<div class="pencil-guide__vis" aria-hidden="true"><?php self::render_guide_site( true ); ?></div>
							</div>

							<div class="pencil-guide__row">
								<div><span class="pencil-guide__n">3</span><h3><?php esc_html_e( 'They edit, or leave a comment', 'pencilino' ); ?></h3><p><?php esc_html_e( 'Edit saves the change. Comment sends you a private note about that exact spot.', 'pencilino' ); ?></p></div>
								<div class="pencil-guide__vis" aria-hidden="true">
									<div class="pencil-gpop"><div class="pencil-gpop__head"><span><span class="pencil-gpop__tab is-on"><?php esc_html_e( 'Edit', 'pencilino' ); ?></span><span class="pencil-gpop__tab"><?php esc_html_e( 'Comment', 'pencilino' ); ?></span></span><span class="dashicons dashicons-no-alt"></span></div><span class="pencil-gpop__field"><?php esc_html_e( 'Freshly roasted coffee', 'pencilino' ); ?></span><span class="pencil-gpop__btn"><?php esc_html_e( 'Save', 'pencilino' ); ?></span></div>
									<div class="pencil-gpop"><div class="pencil-gpop__head"><span><span class="pencil-gpop__tab"><?php esc_html_e( 'Edit', 'pencilino' ); ?></span><span class="pencil-gpop__tab is-on"><?php esc_html_e( 'Comment', 'pencilino' ); ?></span></span><span class="dashicons dashicons-no-alt"></span></div><span class="pencil-gpop__field pencil-gpop__field--area"><?php esc_html_e( 'Can we make this headline bigger?', 'pencilino' ); ?></span><span class="pencil-gpop__btn"><?php esc_html_e( 'Send', 'pencilino' ); ?></span></div>
								</div>
							</div>

							<div class="pencil-guide__row">
								<div><span class="pencil-guide__n">4</span><h3><?php esc_html_e( 'Dynamic content opens what it needs', 'pencilino' ); ?></h3><p><?php esc_html_e( 'Products, posts and other managed content get a gray outline. Popular dynamic content plugins open their edit screen in the popup, scrolled to the field you clicked and outlined. Their role decides what they may edit: WooCommerce products need the Shop manager role.', 'pencilino' ); ?></p></div>
								<div class="pencil-guide__vis" aria-hidden="true">
									<div class="pencil-gsite pencil-gsite--pad"><div class="pencil-gouter"><span class="pencil-gprod"><i></i><span><strong><?php esc_html_e( 'Cerrado', 'pencilino' ); ?></strong><em><?php esc_html_e( '€14.00 / 250g', 'pencilino' ); ?></em></span></span><span class="pencil-gprod"><i></i><span><strong><?php esc_html_e( 'Kirinyaga', 'pencilino' ); ?></strong><em><?php esc_html_e( '€12.90 / 250g', 'pencilino' ); ?></em></span></span></div></div>
									<span class="dashicons dashicons-arrow-right-alt pencil-guide__arrow"></span>
									<div class="pencil-gpop"><div class="pencil-gpop__head"><span><span class="pencil-gpop__tab is-on"><?php esc_html_e( 'Edit', 'pencilino' ); ?></span><span class="pencil-gpop__tab"><?php esc_html_e( 'Comment', 'pencilino' ); ?></span></span><span class="dashicons dashicons-no-alt"></span></div><span class="pencil-gpop__label"><?php esc_html_e( 'Product name', 'pencilino' ); ?></span><span class="pencil-gpop__field"><?php esc_html_e( 'Cerrado', 'pencilino' ); ?></span><span class="pencil-gpop__label"><?php esc_html_e( 'Price', 'pencilino' ); ?></span><span class="pencil-gpop__field"><?php esc_html_e( '14.00', 'pencilino' ); ?></span><span class="pencil-gpop__btn"><?php esc_html_e( 'Save', 'pencilino' ); ?></span></div>
								</div>
							</div>

							<div class="pencil-guide__row">
								<div><span class="pencil-guide__n">5</span><h3><?php esc_html_e( 'You see what changed', 'pencilino' ); ?></h3><p><?php esc_html_e( 'Every edit is logged with the value before and after, under Changes. Comments land next to it, under Comments.', 'pencilino' ); ?></p></div>
								<div class="pencil-guide__vis" aria-hidden="true">
									<div class="pencil-glog"><span class="pencil-glog__tabs"><span class="is-on"><?php esc_html_e( 'Changes', 'pencilino' ); ?></span><span><?php esc_html_e( 'Comments', 'pencilino' ); ?></span></span><span class="pencil-glog__top"><strong><?php esc_html_e( 'Home headline', 'pencilino' ); ?></strong><em><?php esc_html_e( 'Updated by your client, 10:42', 'pencilino' ); ?></em></span><span class="pencil-glog__diff"><span><?php esc_html_e( 'Fresh coffee', 'pencilino' ); ?></span><span class="dashicons dashicons-arrow-right-alt"></span><span><?php esc_html_e( 'Freshly roasted coffee', 'pencilino' ); ?></span></span></div>
								</div>
							</div>
						</div>

					</div>

				</div>

			</div>
		</div>
		<?php
	}
}
