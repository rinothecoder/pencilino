<?php
/**
 * AI instructions tab: the agent instructions in sections, with room for the site owner's own additions.
 *
 * The default text always comes from docs/agent-instructions.md, so plugin updates reach everyone.
 * What the site owner adds is stored apart from it and placed after the default of its section.
 *
 * @package Pencilino
 */
defined( 'ABSPATH' ) || exit;

final class Pencil_AI_Instructions {

	const OPTION     = 'pencil_ai_additions';
	const MAX_LENGTH = 8000;

	public static function init() {
		add_action( 'admin_post_pencil_save_ai_instructions', array( __CLASS__, 'save' ) );
	}

	/**
	 * The sections of the tab. Each one lists the headings of the instructions file it covers,
	 * in file order, so the sections together make up the whole file.
	 *
	 * @return array
	 */
	private static function groups() {
		return array(
			'front-end'   => array(
				'title'       => __( 'Front-end editing', 'pencilino' ),
				'problem'     => __( 'AI often hard-codes the text and images in the templates, so a client cannot change anything without a developer.', 'pencilino' ),
				'lead'        => __( 'The agent starts from your idea or from your own HTML template, builds the theme, and hands all static content to Pencilino. You and your clients then edit text, buttons and images right on the website. The site keeps working, and shows its content, even when the plugin is switched off.', 'pencilino' ),
				'placeholder' => __( 'Example: Make every heading, text and button editable, also in the footer.', 'pencilino' ),
				'headings'    => array( 'Role', 'Choose the starting path', 'Pencilino theme compatibility contract', 'Pencilino v2 client workflow', 'Hidden states for editors', 'Required safe theme integration', 'Content continuity', 'Non-negotiable rules', 'Decide who owns content before writing code', 'Pencilino-owned content' ),
			),
			'templates'   => array(
				'title'       => __( 'Templates', 'pencilino' ),
				'problem'     => __( 'Agents often paste the header and footer into every page, or forget the 404, search and blog templates. Those then fall back to the plain WordPress look.', 'pencilino' ),
				'lead'        => __( 'One shared header and footer for the whole site, repeated sections in one file, and a 404, search and blog template in your design.', 'pencilino' ),
				'placeholder' => __( 'Example: Always add a simple landing page template without a menu.', 'pencilino' ),
				'headings'    => array( 'Shared parts: header, footer and repeated sections', 'Templates beyond the pages' ),
			),
			'structure'   => array(
				'title'       => __( 'Pages and site basics', 'pencilino' ),
				'problem'     => __( 'A page that only exists as a template does not show up under Pages in WordPress, so you cannot manage it or link to it.', 'pencilino' ),
				'lead'        => __( 'The agent creates a real WordPress page for every page, sets your homepage as the front page, and builds a menu you can edit and reorder. The theme also gets a proper name and screenshot, and a favicon or share image only when you provide one.', 'pencilino' ),
				'placeholder' => __( 'Example: Always add a Contact page and a Privacy page.', 'pencilino' ),
				'headings'    => array( 'Pages, URLs, and templates', 'Navigation', 'Theme identity and presentation', 'Site basics' ),
			),
			'content'     => array(
				'title'       => __( 'Content and images', 'pencilino' ),
				'problem'     => __( 'Hard-coded text and images can only be changed by a developer, and some content, like products, already belongs to another plugin.', 'pencilino' ),
				'lead'        => __( 'Every text and image on the page becomes editable on the front end. Images go into the Media Library in a fixed frame, so replacing a photo never breaks the layout. Content from plugins like ACF or WooCommerce stays where it is. Images come from your own files, free Pexels and Unsplash photos, or the image model of your AI.', 'pencilino' ),
				'placeholder' => __( 'Example: Keep button labels under 30 characters.', 'pencilino' ),
				'headings'    => array( 'Avoid conflicts with WordPress UI on the frontend', 'Required image workflow', 'Managed content from other providers', 'Content in the block editor (Gutenberg)' ),
			),
			'design'      => array(
				'title'       => __( 'Design system', 'pencilino' ),
				'problem'     => __( 'AI-written CSS gets messy fast: values everywhere, classes that fight each other, and a design that is hard to change later.', 'pencilino' ),
				'lead'        => __( 'The agent uses your CSS framework if you bring one. If not, Pencilino gives it a default framework to build with, so the code stays clean. It also adds a private brand guide page.', 'pencilino' ),
				'placeholder' => __( 'Example: Use at most two font families.', 'pencilino' ),
				'headings'    => array( 'Design system rules', 'Default design basics', 'Brand guide page' ),
			),
			'mobile'      => array(
				'title'       => __( 'Mobile', 'pencilino' ),
				'problem'     => __( 'AI-built sections often look fine on a desktop and break on a phone.', 'pencilino' ),
				'lead'        => __( 'Every section works from 360 px wide, and the mobile menu works with touch and keyboard. The focus outline stays visible and animations respect visitors who want less motion.', 'pencilino' ),
				'placeholder' => __( 'Example: Use the breakpoints 768 px and 1200 px.', 'pencilino' ),
				'headings'    => array( 'Mobile and accessibility' ),
			),
			'speed'       => array(
				'title'       => __( 'Speed', 'pencilino' ),
				'problem'     => __( 'Fonts from outside servers and heavy images slow a site down, and fonts from outside servers are a GDPR risk.', 'pencilino' ),
				'lead'        => __( 'Fonts load from your own server, styles and scripts load the WordPress way, and images are resized, converted to WebP and compressed before they are uploaded.', 'pencilino' ),
				'placeholder' => __( 'Example: Only load a script on the pages that use it.', 'pencilino' ),
				'headings'    => array( 'Speed' ),
			),
			'safety'      => array(
				'title'       => __( 'Safety', 'pencilino' ),
				'problem'     => __( 'Text from the database or from a visitor can hide harmful code if a theme prints it as it is.', 'pencilino' ),
				'lead'        => __( 'The agent is told to clean everything it prints and to check that only the right people can save things. It is an instruction, so it helps, but it is not a guarantee.', 'pencilino' ),
				'note'        => __( 'These are only a few basic checks on the code the agent writes. They do not replace a security plugin, so it is recommended to use one as well.', 'pencilino' ),
				'placeholder' => __( 'Example: Never put passwords or API keys in theme files.', 'pencilino' ),
				'headings'    => array( 'Safety' ),
			),
			'seo'         => array(
				'title'       => __( 'SEO', 'pencilino' ),
				'problem'     => __( 'Pencilino pages keep their content in the theme, so SEO plugins like Yoast and Rank Math see an empty page. Google does not mind, but those plugins do.', 'pencilino' ),
				'lead'        => __( 'The agent first checks whether your site already has an SEO plugin, then follows one of two paths. Either way, every page gets one main heading and every image gets alt text.', 'pencilino' ),
				'paths'       => array(
					'A' => array(
						'title' => __( 'You have an SEO plugin', 'pencilino' ),
						'text'  => __( 'The plugin owns your titles, descriptions and sitemap. The agent only gives it the text of your pages, so its content checks work, and it prints no SEO tags itself.', 'pencilino' ),
					),
					'B' => array(
						'title' => __( 'You have no SEO plugin', 'pencilino' ),
						'text'  => __( 'Pencilino adds an SEO box to every page and post with a title and a meta description. The agent fills it in from the real page content.', 'pencilino' ),
					),
				),
				'placeholder' => __( 'Example: Write SEO titles in Dutch and keep them under 55 characters.', 'pencilino' ),
				'headings'    => array( 'SEO' ),
			),
			'checks'      => array(
				'title'       => __( 'Final checks', 'pencilino' ),
				'problem'     => __( 'Without a check, a theme can look finished and still miss pages, have the wrong images or broken edit buttons.', 'pencilino' ),
				'lead'        => __( 'The agent runs a checklist before it calls a theme done, and follows a list of things it must never do.', 'pencilino' ),
				'placeholder' => __( 'Example: Also check the site at 320 px wide.', 'pencilino' ),
				'headings'    => array( 'Required checks before completing a theme change', 'Never do these things' ),
			),
		);
	}

	/**
	 * The instructions file cut into the sections above, each with its default text and a hash of it.
	 * A heading the sections do not know stays with the section before it, so no text gets lost.
	 *
	 * @return array
	 */
	public static function sections() {
		static $sections = null;
		if ( null !== $sections ) {
			return $sections;
		}

		$sections = array();
		$groups   = self::groups();
		$file     = PENCIL_PLUGIN_PATH . 'docs/agent-instructions.md';
		$text     = file_exists( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		// The notice at the top of the file is for agents that read the raw file. It is not part of any section.
		$text     = (string) preg_replace( '/<!-- pencilino:file-notice -->.*?<!-- \/pencilino:file-notice -->\s*/s', '', $text );
		if ( '' === trim( $text ) ) {
			return $sections;
		}

		$map = array();
		foreach ( $groups as $slug => $group ) {
			foreach ( $group['headings'] as $heading ) {
				$map[ $heading ] = $slug;
			}
		}

		$lines   = array_fill_keys( array_keys( $groups ), array() );
		$current = key( $groups );
		$in_code = false;
		foreach ( explode( chr( 10 ), $text ) as $line ) {
			if ( 0 === strpos( ltrim( $line ), '```' ) ) {
				$in_code = ! $in_code;
			}
			if ( ! $in_code && 0 === strpos( $line, '## ' ) ) {
				$heading = trim( substr( $line, 3 ) );
				if ( isset( $map[ $heading ] ) ) {
					$current = $map[ $heading ];
				}
			}
			$lines[ $current ][] = $line;
		}

		foreach ( $groups as $slug => $group ) {
			$default = implode( chr( 10 ), $lines[ $slug ] );
			if ( '' === trim( $default ) ) {
				continue;
			}
			$group['default']  = $default;
			$group['hash']     = md5( trim( $default ) );
			$sections[ $slug ] = $group;
		}

		return $sections;
	}

	/** All saved additions: section => array( text, seen, time ). */
	private static function records() {
		$records = get_option( self::OPTION, array() );
		if ( ! is_array( $records ) ) {
			return array();
		}
		// The Basics and Theme rules tabs became Front-end editing. Additions saved there move along.
		$moved = false;
		foreach ( array( 'basics', 'theme-rules' ) as $old ) {
			if ( ! isset( $records[ $old ] ) ) {
				continue;
			}
			$text = isset( $records[ $old ]['text'] ) ? trim( (string) $records[ $old ]['text'] ) : '';
			if ( '' !== $text ) {
				$have = isset( $records['front-end']['text'] ) ? trim( (string) $records['front-end']['text'] ) : '';
				$records['front-end'] = array(
					'text' => '' === $have ? $text : $have . chr( 10 ) . chr( 10 ) . $text,
					'seen' => isset( $records['front-end']['seen'] ) ? $records['front-end']['seen'] : ( isset( $records[ $old ]['seen'] ) ? $records[ $old ]['seen'] : '' ),
					'time' => isset( $records[ $old ]['time'] ) ? $records[ $old ]['time'] : time(),
				);
			}
			unset( $records[ $old ] );
			$moved = true;
		}
		if ( $moved ) {
			update_option( self::OPTION, $records, false );
		}
		return $records;
	}

	/** The site owner's additions to one section. */
	public static function additions( $slug ) {
		$records = self::records();
		return isset( $records[ $slug ]['text'] ) ? (string) $records[ $slug ]['text'] : '';
	}

	/** Whether the default of a section changed since the site owner last looked, while they have additions there. */
	private static function default_changed( $slug ) {
		$sections = self::sections();
		$records  = self::records();
		if ( ! isset( $sections[ $slug ], $records[ $slug ] ) || empty( $records[ $slug ]['text'] ) ) {
			return false;
		}
		return isset( $records[ $slug ]['seen'] ) && $records[ $slug ]['seen'] !== $sections[ $slug ]['hash'];
	}

	/**
	 * The instructions of one section as an agent should read them: the default, followed by the additions of the site owner.
	 *
	 * @param string $slug Section slug.
	 * @return string
	 */
	public static function section_text( $slug ) {
		$sections = self::sections();
		if ( ! isset( $sections[ $slug ] ) ) {
			return '';
		}
		$chunk = $sections[ $slug ]['default'];
		$extra = trim( self::additions( $slug ) );
		if ( '' !== $extra ) {
			$chunk = rtrim( $chunk ) . chr( 10 ) . chr( 10 ) . '## Site owner additions: ' . $sections[ $slug ]['title'] . chr( 10 ) . chr( 10 ) . 'The site owner wrote the following for this part. If it conflicts with the instructions above, follow it.' . chr( 10 ) . chr( 10 ) . $extra . chr( 10 );
		}
		return $chunk;
	}

	/**
	 * The complete instructions as an agent should read them: every default, each followed by the additions of its section.
	 * Without additions this is the instructions file, unchanged.
	 *
	 * @return string
	 */
	public static function full_text() {
		$chunks = array();
		foreach ( array_keys( self::sections() ) as $slug ) {
			$chunks[] = self::section_text( $slug );
		}
		return implode( chr( 10 ), $chunks );
	}

	/**
	 * The sections in file order: slug, title and whether the site owner added something.
	 *
	 * @return array
	 */
	public static function overview() {
		$list = array();
		foreach ( self::sections() as $slug => $section ) {
			$list[] = array(
				'slug'          => $slug,
				'title'         => $section['title'],
				'has_additions' => '' !== trim( self::additions( $slug ) ),
			);
		}
		return $list;
	}

	/**
	 * Save, clear or acknowledge the additions of one section.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! Pencil_Client::can_manage() ) {
			wp_die( esc_html__( 'You cannot change these settings.', 'pencilino' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'pencil_save_ai_instructions' );

		$slug     = isset( $_POST['pencil_ai_section'] ) ? sanitize_key( wp_unslash( $_POST['pencil_ai_section'] ) ) : '';
		$op       = isset( $_POST['pencil_ai_op'] ) ? sanitize_key( wp_unslash( $_POST['pencil_ai_op'] ) ) : 'save';
		$sections = self::sections();

		if ( isset( $sections[ $slug ] ) ) {
			$records = self::records();
			$current = isset( $records[ $slug ] ) && is_array( $records[ $slug ] ) ? $records[ $slug ] : array();
			$had     = ! empty( $current['text'] );

			if ( 'clear' === $op ) {
				unset( $records[ $slug ] );
			} elseif ( 'seen' === $op ) {
				if ( $had ) {
					$records[ $slug ]['seen'] = $sections[ $slug ]['hash'];
				}
			} else {
				// Plain text, kept as typed (an instruction may mention tags like main or h1). It is only ever printed escaped.
				$text = isset( $_POST['pencil_ai_text'] ) ? wp_check_invalid_utf8( wp_unslash( $_POST['pencil_ai_text'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$text = trim( str_replace( array( chr( 13 ) . chr( 10 ), chr( 13 ) ), chr( 10 ), (string) $text ) );
				$text = mb_substr( $text, 0, self::MAX_LENGTH );

				if ( '' === $text ) {
					unset( $records[ $slug ] );
				} else {
					$records[ $slug ] = array(
						'text' => $text,
						'seen' => $had && isset( $current['seen'] ) ? $current['seen'] : $sections[ $slug ]['hash'],
						'time' => time(),
					);
				}
			}

			if ( $records ) {
				update_option( self::OPTION, $records, false );
			} else {
				delete_option( self::OPTION );
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => Pencil_Admin::MENU_SLUG, 'pencil-settings-saved' => '1' ), admin_url( 'admin.php' ) ) . '#ai-' . $slug );
		exit;
	}

	/** The hidden fields every form of this tab needs. */
	private static function fields( $slug, $op ) {
		printf(
			'<input type="hidden" name="action" value="pencil_save_ai_instructions"><input type="hidden" name="pencil_ai_section" value="%1$s"><input type="hidden" name="_wpnonce" value="%2$s">',
			esc_attr( $slug ),
			esc_attr( wp_create_nonce( 'pencil_save_ai_instructions' ) )
		);
		if ( '' !== $op ) {
			printf( '<input type="hidden" name="pencil_ai_op" value="%s">', esc_attr( $op ) );
		}
	}

	/**
	 * The tab: a sidebar with one item per section, and for each section an explanation,
	 * the read-only default and a field for the site owner's own additions.
	 *
	 * @return void
	 */
	public static function render() {
		$sections = self::sections();

		if ( ! $sections ) {
			?>
			<div class="pencil-content"><div class="pencil-card pencil-card--full">
				<p><?php esc_html_e( 'The instructions file could not be found. Reinstall Pencilino to restore it.', 'pencilino' ); ?></p>
			</div></div>
			<?php
			return;
		}

		$records = self::records();
		$first   = key( $sections );
		$action  = admin_url( 'admin-post.php' );
		?>
		<div class="pencil-shell" data-subtabs>
			<nav class="pencil-side" aria-label="<?php esc_attr_e( 'AI instruction sections', 'pencilino' ); ?>">
				<?php foreach ( $sections as $slug => $section ) : ?>
					<?php
					$updated = self::default_changed( $slug );
					$dot     = 'pencil-side__dot' . ( ( $updated || '' !== self::additions( $slug ) ) ? ' show' : '' ) . ( $updated ? ' pencil-side__dot--update' : '' );
					?>
					<button type="button" class="pencil-side__item<?php echo $slug === $first ? ' pencil-subtabs__btn--active' : ''; ?>" data-subtab="<?php echo esc_attr( $slug ); ?>" data-subtab-hash="ai-<?php echo esc_attr( $slug ); ?>" aria-selected="<?php echo $slug === $first ? 'true' : 'false'; ?>"><span><?php echo esc_html( $section['title'] ); ?></span><i class="<?php echo esc_attr( $dot ); ?>"></i></button>
				<?php endforeach; ?>
				<button type="button" class="pencil-side__item" data-subtab="cleanup" data-subtab-hash="ai-cleanup" aria-selected="false"><span><?php esc_html_e( 'Clean-up', 'pencilino' ); ?></span><i class="pencil-side__dot"></i></button>
			</nav>

			<div class="pencil-main">
				<?php foreach ( $sections as $slug => $section ) : ?>
					<?php
					$text    = self::additions( $slug );
					$updated = self::default_changed( $slug );
					$time    = isset( $records[ $slug ]['time'] ) ? (int) $records[ $slug ]['time'] : 0;
					?>
					<div data-subtab-panel="<?php echo esc_attr( $slug ); ?>"<?php echo $slug === $first ? '' : ' hidden'; ?>>
						<div class="pencil-content"><div class="pencil-card pencil-card--full">
							<div class="pencil-ai__head">
								<?php Pencil_Admin_UI::intro( __( 'AI instructions', 'pencilino' ), $section['title'] ); ?>
							</div>

							<div class="pencil-ai__what">
								<p><strong><?php esc_html_e( 'The problem:', 'pencilino' ); ?></strong> <?php echo esc_html( $section['problem'] ); ?></p>
								<p><strong><?php esc_html_e( 'The solution:', 'pencilino' ); ?></strong> <?php echo esc_html( $section['lead'] ); ?></p>
								<?php if ( ! empty( $section['note'] ) ) : ?>
									<p><strong><?php esc_html_e( 'Good to know:', 'pencilino' ); ?></strong> <?php echo esc_html( $section['note'] ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $section['paths'] ) ) : ?>
									<div class="pencil-ai__paths">
										<?php foreach ( $section['paths'] as $letter => $path ) : ?>
											<div class="pencil-ai__path"><strong><span class="pencil-ai__tag"><?php echo esc_html( $letter ); ?></span><?php echo esc_html( $path['title'] ); ?></strong><p><?php echo esc_html( $path['text'] ); ?></p></div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>

							<?php if ( $updated ) : ?>
								<form class="pencil-ai__notice" action="<?php echo esc_url( $action ); ?>" method="post">
									<?php self::fields( $slug, 'seen' ); ?>
									<p><strong><?php esc_html_e( 'The default for this section changed.', 'pencilino' ); ?></strong> <?php esc_html_e( 'Your additions are untouched. Open the default below to see what is new.', 'pencilino' ); ?></p>
									<button type="submit" class="pencil-button pencil-button--quiet"><?php esc_html_e( 'Got it', 'pencilino' ); ?></button>
								</form>
							<?php endif; ?>

							<details class="pencil-ai__default">
								<summary><strong><?php esc_html_e( 'Default instructions', 'pencilino' ); ?></strong><span><?php esc_html_e( 'Read only. Updated with the plugin.', 'pencilino' ); ?></span></summary>
								<textarea class="pencil-instructions__text" readonly spellcheck="false" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: section name */ __( 'Default instructions: %s', 'pencilino' ), $section['title'] ) ); ?>"><?php echo esc_textarea( rtrim( $section['default'] ) ); ?></textarea>
							</details>


							<form class="pencil-ai__form" action="<?php echo esc_url( $action ); ?>" method="post">
								<?php self::fields( $slug, '' ); ?>
								<label for="pencil-ai-text-<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Your additions', 'pencilino' ); ?></label>
								<p class="pencil-focus__sub"><?php esc_html_e( 'Added after the default. If your text conflicts with the default, yours wins.', 'pencilino' ); ?></p>
								<textarea id="pencil-ai-text-<?php echo esc_attr( $slug ); ?>" name="pencil_ai_text" rows="6" maxlength="<?php echo esc_attr( self::MAX_LENGTH ); ?>" spellcheck="false" placeholder="<?php echo esc_attr( $section['placeholder'] ); ?>"><?php echo esc_textarea( $text ); ?></textarea>
								<div class="pencil-ai__actions">
									<span class="pencil-ai__meta"><?php echo $time ? esc_html( sprintf( /* translators: %s: time since the last save, such as 2 days */ __( 'Last saved %s ago', 'pencilino' ), human_time_diff( $time ) ) ) : esc_html__( 'Nothing added yet', 'pencilino' ); ?></span>
									<span>
										<?php if ( '' !== $text ) : ?>
											<button type="submit" name="pencil_ai_op" value="clear" class="pencil-button pencil-button--quiet"><?php esc_html_e( 'Clear additions', 'pencilino' ); ?></button>
										<?php endif; ?>
										<button type="submit" name="pencil_ai_op" value="save" class="pencil-button"><?php esc_html_e( 'Save', 'pencilino' ); ?></button>
									</span>
								</div>
							</form>
						</div></div>
					</div>
				<?php endforeach; ?>

				<div data-subtab-panel="cleanup" hidden>
					<div class="pencil-content"><div class="pencil-card pencil-card--full">
						<div class="pencil-ai__head">
							<?php Pencil_Admin_UI::intro( __( 'AI instructions', 'pencilino' ), __( 'Clean-up', 'pencilino' ) ); ?>
						</div>
						<div class="pencil-ai__what">
							<p><strong><?php esc_html_e( 'The problem:', 'pencilino' ); ?></strong> <?php esc_html_e( 'AI makes mistakes. While it builds, it skips steps, jumps ahead and forgets parts of these instructions.', 'pencilino' ); ?></p>
							<p><strong><?php esc_html_e( 'The solution:', 'pencilino' ); ?></strong> <?php esc_html_e( 'One prompt that makes your agent read all the instructions again, check the whole site against them and fix what slipped. The site keeps looking the same, and the agent ends with a short summary in non-technical words.', 'pencilino' ); ?></p>
							<p><strong><?php esc_html_e( 'Good to know:', 'pencilino' ); ?></strong> <?php esc_html_e( 'Use it before you launch and after bigger changes. Your own additions in the other tabs are part of the check.', 'pencilino' ); ?></p>
						</div>
						<div class="pencil-ai__form">
							<label for="pencil-cleanup-prompt"><?php esc_html_e( 'Clean-up prompt', 'pencilino' ); ?></label>
							<p class="pencil-focus__sub"><?php esc_html_e( 'Adjust it here before you copy it. Your changes are only for this copy and are not saved.', 'pencilino' ); ?></p>
							<textarea id="pencil-cleanup-prompt" rows="16" spellcheck="false"><?php echo esc_textarea( Pencil_Dev::master_prompt() ); ?></textarea>
							<div class="pencil-ai__actions">
								<span></span>
								<button type="button" class="pencil-button" data-copy="pencil-cleanup-prompt" data-copied-label="<?php esc_attr_e( 'Prompt copied', 'pencilino' ); ?>"><?php esc_html_e( 'Copy clean-up prompt', 'pencilino' ); ?></button>
							</div>
						</div>
					</div></div>
				</div>
			</div>
		</div>
		<?php
	}
}
