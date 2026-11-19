<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Drek\Component\Drekmeta\Administrator\View\Item\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_drekmeta');
$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('com_drekmeta.item-edit')
    ->useStyle('com_drekmeta.admin');

$this->getDocument()->addScriptOptions('com_drekmeta.item', $this->builderOptions);

// Every string the edit screen JavaScript uses.
foreach (
    [
        'JCLOSE',
        'COM_DREKMETA_BUILDER_ACCENT', 'COM_DREKMETA_BUILDER_ACCENT_ON', 'COM_DREKMETA_BUILDER_ACCENT_SYNC', 'COM_DREKMETA_BUILDER_ACCESSORY',
        'COM_DREKMETA_BUILDER_ADD', 'COM_DREKMETA_BUILDER_ADD_BUTTON', 'COM_DREKMETA_BUILDER_ADD_ITEM', 'COM_DREKMETA_BUILDER_ADD_TEXT',
        'COM_DREKMETA_BUILDER_ALT', 'COM_DREKMETA_BUILDER_BUTTON_DISABLED', 'COM_DREKMETA_BUILDER_BUTTON_EMOJI', 'COM_DREKMETA_BUILDER_BUTTON_EMOJI_HELP',
        'COM_DREKMETA_BUILDER_BUTTON_LABEL', 'COM_DREKMETA_BUILDER_BUTTON_N', 'COM_DREKMETA_BUILDER_BUTTON_URL', 'COM_DREKMETA_BUILDER_COLLAPSE',
        'COM_DREKMETA_BUILDER_COMPONENT_N', 'COM_DREKMETA_BUILDER_CONTAINER', 'COM_DREKMETA_BUILDER_CONTAINER_HINT', 'COM_DREKMETA_BUILDER_COPIED',
        'COM_DREKMETA_BUILDER_COUNTER_BYTES', 'COM_DREKMETA_BUILDER_COUNTER_COMPONENTS', 'COM_DREKMETA_BUILDER_COUNTER_GALLERY',
        'COM_DREKMETA_BUILDER_DEBUGGER', 'COM_DREKMETA_BUILDER_DEBUGGER_NOTE', 'COM_DREKMETA_BUILDER_DELETE', 'COM_DREKMETA_BUILDER_DIVIDER',
        'COM_DREKMETA_BUILDER_DIVIDER_SHOW', 'COM_DREKMETA_BUILDER_DRAG', 'COM_DREKMETA_BUILDER_DUPLICATE', 'COM_DREKMETA_BUILDER_EMPTY_TEXT',
        'COM_DREKMETA_BUILDER_EMPTY_TEXT_SUMMARY', 'COM_DREKMETA_BUILDER_EMPTY_TITLE', 'COM_DREKMETA_BUILDER_EXPAND', 'COM_DREKMETA_BUILDER_GALLERY_FULL',
        'COM_DREKMETA_BUILDER_IMAGE_URL', 'COM_DREKMETA_BUILDER_ITEM_N', 'COM_DREKMETA_BUILDER_JSON_APPLIED', 'COM_DREKMETA_BUILDER_JSON_APPLY',
        'COM_DREKMETA_BUILDER_JSON_COPY', 'COM_DREKMETA_BUILDER_JSON_COPY_OUTPUT', 'COM_DREKMETA_BUILDER_JSON_DISCARD', 'COM_DREKMETA_BUILDER_JSON_EDITABLE',
        'COM_DREKMETA_BUILDER_JSON_FORMAT', 'COM_DREKMETA_BUILDER_JSON_IMPORT', 'COM_DREKMETA_BUILDER_JSON_INVALID', 'COM_DREKMETA_BUILDER_JSON_IN_SYNC',
        'COM_DREKMETA_BUILDER_JSON_OUTPUT', 'COM_DREKMETA_BUILDER_JSON_OUTPUT_BYTES', 'COM_DREKMETA_BUILDER_JSON_OUTPUT_NOTE', 'COM_DREKMETA_BUILDER_JSON_UNAPPLIED',
        'COM_DREKMETA_BUILDER_LARGE_IMAGE', 'COM_DREKMETA_BUILDER_LOAD_CLEANED', 'COM_DREKMETA_BUILDER_MARKDOWN', 'COM_DREKMETA_BUILDER_MARKDOWN_HINT',
        'COM_DREKMETA_BUILDER_MEDIA_HELP_IMAGE', 'COM_DREKMETA_BUILDER_MEDIA_HELP_VIDEO', 'COM_DREKMETA_BUILDER_MEDIA_URL', 'COM_DREKMETA_BUILDER_MOVE_DOWN',
        'COM_DREKMETA_BUILDER_MOVE_UP', 'COM_DREKMETA_BUILDER_NO_DIVIDER', 'COM_DREKMETA_BUILDER_NO_THEME_COLOR', 'COM_DREKMETA_BUILDER_N_BUTTONS',
        'COM_DREKMETA_BUILDER_N_ITEMS', 'COM_DREKMETA_BUILDER_PICK', 'COM_DREKMETA_BUILDER_PICK_UNAVAILABLE', 'COM_DREKMETA_BUILDER_PLACEHOLDERS',
        'COM_DREKMETA_BUILDER_PLACEHOLDER_INSERT', 'COM_DREKMETA_BUILDER_PREVIEW_DISABLED', 'COM_DREKMETA_BUILDER_ROOT', 'COM_DREKMETA_BUILDER_SAVE_BLOCKED',
        'COM_DREKMETA_BUILDER_SAVE_BLOCKED_CLIENT', 'COM_DREKMETA_BUILDER_SAVE_ERRORS', 'COM_DREKMETA_BUILDER_SECTION_TEXTS', 'COM_DREKMETA_BUILDER_SPACING',
        'COM_DREKMETA_BUILDER_SPACING_LARGE', 'COM_DREKMETA_BUILDER_SPACING_SMALL', 'COM_DREKMETA_BUILDER_SPOILER', 'COM_DREKMETA_BUILDER_SPOILER_CONTAINER',
        'COM_DREKMETA_BUILDER_STARTER_BUTTON', 'COM_DREKMETA_BUILDER_START_CONFIRM', 'COM_DREKMETA_BUILDER_START_FROM_PAGE', 'COM_DREKMETA_BUILDER_START_NO_IMAGE',
        'COM_DREKMETA_BUILDER_STATUS_ERRORS', 'COM_DREKMETA_BUILDER_STATUS_OFF', 'COM_DREKMETA_BUILDER_STATUS_OK', 'COM_DREKMETA_BUILDER_SUBTITLE',
        'COM_DREKMETA_BUILDER_TAB_JSON', 'COM_DREKMETA_BUILDER_TAB_PREVIEW', 'COM_DREKMETA_BUILDER_TEXT_N', 'COM_DREKMETA_BUILDER_TITLE',
        'COM_DREKMETA_COUNTER_DESCRIPTION', 'COM_DREKMETA_COUNTER_TITLE', 'COM_DREKMETA_FALLBACK_DESCRIPTION', 'COM_DREKMETA_FALLBACK_SITE_NAME',
        'COM_DREKMETA_FALLBACK_TITLE', 'COM_DREKMETA_INHERIT_WITH_VALUE', 'COM_DREKMETA_INHERITED', 'COM_DREKMETA_INHERITED_PLACEHOLDER', 'COM_DREKMETA_PAGE_URL',
        'COM_DREKMETA_PREVIEW_COMPONENT_DISABLED', 'COM_DREKMETA_PREVIEW_COMPONENT_INVALID', 'COM_DREKMETA_PREVIEW_EMPTY_EMBED', 'COM_DREKMETA_PREVIEW_MASTER_OFF',
        'COM_DREKMETA_PREVIEW_NO_MEDIA', 'COM_DREKMETA_PREVIEW_SPOILER', 'COM_DREKMETA_PREVIEW_SPOILER_REVEAL', 'COM_DREKMETA_PREVIEW_TIMESTAMP',
        'COM_DREKMETA_PREVIEW_USERNAME', 'COM_DREKMETA_SOURCE_CUSTOM', 'COM_DREKMETA_SOURCE_FALLBACK', 'COM_DREKMETA_SOURCE_INHERITED', 'COM_DREKMETA_SOURCE_ITEM',
        'COM_DREKMETA_TYPE_BUTTON', 'COM_DREKMETA_TYPE_GALLERY', 'COM_DREKMETA_TYPE_ROW', 'COM_DREKMETA_TYPE_SECTION', 'COM_DREKMETA_TYPE_SEPARATOR',
        'COM_DREKMETA_TYPE_TEXT', 'COM_DREKMETA_TYPE_THUMBNAIL', 'COM_DREKMETA_TYPE_UNKNOWN',
        'COM_DREKMETA_EMBED_ERR_ACCENT_COLOR', 'COM_DREKMETA_EMBED_ERR_BUTTON_KEY', 'COM_DREKMETA_EMBED_ERR_BUTTON_LABEL', 'COM_DREKMETA_EMBED_ERR_BUTTON_STYLE',
        'COM_DREKMETA_EMBED_ERR_CONTAINER_EMPTY', 'COM_DREKMETA_EMBED_ERR_DESCRIPTION_LENGTH', 'COM_DREKMETA_EMBED_ERR_EMPTY', 'COM_DREKMETA_EMBED_ERR_ENABLED_EMPTY',
        'COM_DREKMETA_EMBED_ERR_GALLERY_EMPTY', 'COM_DREKMETA_EMBED_ERR_INVALID_COMPONENT', 'COM_DREKMETA_EMBED_ERR_JSON', 'COM_DREKMETA_EMBED_ERR_LABEL_LENGTH',
        'COM_DREKMETA_EMBED_ERR_MEDIA_EXTENSION', 'COM_DREKMETA_EMBED_ERR_NESTED_CONTAINER', 'COM_DREKMETA_EMBED_ERR_NOT_ALLOWED_HERE', 'COM_DREKMETA_EMBED_ERR_NO_ROOT',
        'COM_DREKMETA_EMBED_ERR_ROOT_NOT_CONTAINER', 'COM_DREKMETA_EMBED_ERR_ROW_BUTTON_COUNT', 'COM_DREKMETA_EMBED_ERR_ROW_CHILD', 'COM_DREKMETA_EMBED_ERR_SECTION_ACCESSORY',
        'COM_DREKMETA_EMBED_ERR_SECTION_CHILD', 'COM_DREKMETA_EMBED_ERR_SECTION_TEXT_COUNT', 'COM_DREKMETA_EMBED_ERR_SEPARATOR_SPACING', 'COM_DREKMETA_EMBED_ERR_TEXT_EMPTY',
        'COM_DREKMETA_EMBED_ERR_TEXT_EMPTY_RESOLVED', 'COM_DREKMETA_EMBED_ERR_TOO_MANY_BYTES', 'COM_DREKMETA_EMBED_ERR_TOO_MANY_COMPONENTS',
        'COM_DREKMETA_EMBED_ERR_TOO_MANY_GALLERY_ITEMS', 'COM_DREKMETA_EMBED_ERR_UNKNOWN_TYPE', 'COM_DREKMETA_EMBED_ERR_URL_EMPTY_RESOLVED',
        'COM_DREKMETA_EMBED_ERR_URL_LENGTH', 'COM_DREKMETA_EMBED_ERR_URL_PLACEHOLDER', 'COM_DREKMETA_EMBED_ERR_URL_REQUIRED', 'COM_DREKMETA_EMBED_ERR_URL_SCHEME',
    ] as $key
) {
    Text::script($key);
}

$isGlobal = $this->isGlobal;
$master   = $this->builderOptions['fallbacks']['masterSwitch'];
?>
<form action="<?php echo Route::_('index.php?option=com_drekmeta&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="item-form" aria-label="<?php echo Text::_('COM_DREKMETA_MANAGER_ITEM_' . ((int) $this->item->id === 0 ? 'NEW' : 'EDIT'), true); ?>" class="form-validate">

	<?php if ($isGlobal) : ?>
		<div class="alert alert-info drekmeta-global-note">
			<span class="icon-globe" aria-hidden="true"></span>
			<strong><?php echo Text::_('COM_DREKMETA_GLOBAL_DEFAULTS'); ?></strong> &middot;
			<?php echo Text::_('COM_DREKMETA_GLOBAL_DEFAULTS_DESC'); ?>
		</div>
	<?php endif; ?>

	<div class="main-card drekmeta-edit">
		<?php echo HTMLHelper::_('uitab.startTabSet', 'drekmetaTab', ['active' => $isGlobal ? 'meta' : 'target', 'recall' => true, 'breakpoint' => 768]); ?>

		<?php if (!$isGlobal) : ?>
			<?php echo HTMLHelper::_('uitab.addTab', 'drekmetaTab', 'target', Text::_('COM_DREKMETA_TAB_TARGET')); ?>
			<div class="row">
				<div class="col-lg-8">
					<fieldset class="options-form">
						<legend><?php echo Text::_('COM_DREKMETA_TAB_TARGET'); ?></legend>
						<?php echo $this->form->renderField('menu_item_id'); ?>
						<?php echo $this->form->renderField('title'); ?>
					</fieldset>
				</div>
				<div class="col-lg-4">
					<fieldset class="options-form">
						<legend><?php echo Text::_('JDETAILS'); ?></legend>
						<?php echo $this->form->renderField('state'); ?>
						<?php echo $this->form->renderField('id'); ?>
					</fieldset>
				</div>
			</div>
			<?php echo HTMLHelper::_('uitab.endTab'); ?>
		<?php endif; ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'drekmetaTab', 'meta', Text::_('COM_DREKMETA_TAB_META')); ?>
		<div class="row">
			<div class="col-lg-7">
				<fieldset class="options-form">
					<legend><?php echo Text::_('COM_DREKMETA_FIELDSET_OPEN_GRAPH'); ?></legend>
					<?php echo $this->form->renderField('og_title'); ?>
					<?php echo $this->form->renderField('og_description'); ?>
					<?php echo $this->form->renderField('og_image'); ?>
					<?php echo $this->form->renderField('og_image_alt'); ?>
					<?php echo $this->form->renderField('og_type'); ?>
					<?php echo $this->form->renderField('og_site_name'); ?>
				</fieldset>
			</div>
			<div class="col-lg-5">
				<fieldset class="options-form">
					<legend><?php echo Text::_('COM_DREKMETA_FIELDSET_CARD_COLOR'); ?></legend>
					<?php echo $this->form->renderField('twitter_card'); ?>
					<?php echo $this->form->renderField('theme_color'); ?>
				</fieldset>
				<?php if (!$isGlobal) : ?>
					<p class="small text-muted drekmeta-inherit-help">
						<span class="icon-info-circle" aria-hidden="true"></span>
						<?php echo Text::_('COM_DREKMETA_INHERIT_HELP'); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'drekmetaTab', 'custom', Text::_('COM_DREKMETA_TAB_CUSTOM')); ?>
		<fieldset class="options-form">
			<legend><?php echo Text::_('COM_DREKMETA_TAB_CUSTOM'); ?></legend>
			<p class="text-muted"><?php echo Text::_('COM_DREKMETA_CUSTOM_HELP'); ?></p>
			<?php echo $this->form->renderField('custom_tags'); ?>
		</fieldset>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'drekmetaTab', 'embed', Text::_('COM_DREKMETA_TAB_EMBED')); ?>
		<?php if (!$master) : ?>
			<div class="alert alert-warning"><?php echo Text::_('COM_DREKMETA_MASTER_SWITCH_OFF'); ?></div>
		<?php endif; ?>
		<div class="drekmeta-embed-enable">
			<?php echo $this->form->renderField('component_embed_enabled'); ?>
		</div>
		<div id="drekmeta-builder" class="drekmeta-builder-root">
			<noscript><?php echo Text::_('COM_DREKMETA_BUILDER_NOSCRIPT'); ?></noscript>
		</div>
		<div class="d-none">
			<?php echo $this->form->getInput('component_embed'); ?>
		</div>
		<div id="drekmeta-media-picker" class="visually-hidden" aria-hidden="true">
			<?php echo $this->form->getInput('embed_media_picker'); ?>
		</div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'drekmetaTab', 'preview', Text::_('COM_DREKMETA_TAB_PREVIEW')); ?>
		<div id="drekmeta-preview" class="drekmeta-preview">
			<div class="drekmeta-preview-head">
				<div class="dm-segs" role="group" aria-label="<?php echo Text::_('COM_DREKMETA_TAB_PREVIEW', true); ?>">
					<button type="button" class="dm-seg is-active" data-preview-mode="standard" aria-pressed="true"><?php echo Text::_('COM_DREKMETA_PREVIEW_STANDARD'); ?></button>
					<button type="button" class="dm-seg" data-preview-mode="component" aria-pressed="false"><?php echo Text::_('COM_DREKMETA_PREVIEW_COMPONENT'); ?></button>
				</div>
				<span class="small text-muted"><?php echo Text::_('COM_DREKMETA_PREVIEW_APPROX'); ?></span>
			</div>
			<div class="drekmeta-preview-note"></div>
			<div class="drekmeta-preview-grid">
				<div class="drekmeta-preview-canvas dm-preview-canvas"></div>
				<div class="drekmeta-preview-tags">
					<h3 class="h6"><?php echo Text::_('COM_DREKMETA_PREVIEW_TAGS'); ?></h3>
					<table class="table table-sm drekmeta-tags">
						<thead>
							<tr>
								<th scope="col"><?php echo Text::_('COM_DREKMETA_PREVIEW_TAG'); ?></th>
								<th scope="col"><?php echo Text::_('COM_DREKMETA_PREVIEW_VALUE'); ?></th>
								<th scope="col"><?php echo Text::_('COM_DREKMETA_PREVIEW_SOURCE'); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
					<p class="small text-muted"><?php echo Text::_('COM_DREKMETA_PREVIEW_TAGS_NOTE'); ?></p>
				</div>
			</div>
		</div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
	</div>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
