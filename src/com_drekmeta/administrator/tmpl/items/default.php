<?php

/**
 * @package     DREK Meta
 * @author      drek124 <https://github.com/drek124>
 * @copyright   (C) 2026 drek124
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Drek\Component\Drekmeta\Administrator\Helper\MetaHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Drek\Component\Drekmeta\Administrator\View\Items\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect')
    ->getRegistry()->addExtensionRegistryFile('com_drekmeta');
$wa->useStyle('com_drekmeta.admin');

$user      = $this->getCurrentUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>
<form action="<?php echo Route::_('index.php?option=com_drekmeta&view=items'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
						<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
					<table class="table drekmeta-list" id="itemList">
						<caption class="visually-hidden">
							<?php echo Text::_('COM_DREKMETA_ITEMS_TABLE_CAPTION'); ?>,
							<span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
							<span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
						</caption>
						<thead>
							<tr>
								<td class="w-1 text-center">
									<?php echo HTMLHelper::_('grid.checkall'); ?>
								</td>
								<th scope="col">
									<?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
								</th>
								<th scope="col" class="d-none d-md-table-cell">
									<?php echo HTMLHelper::_('searchtools.sort', 'COM_DREKMETA_HEADING_MENU_ITEM', 'm.title', $listDirn, $listOrder); ?>
								</th>
								<th scope="col" class="w-10 d-none d-lg-table-cell text-center">
									<?php echo Text::_('COM_DREKMETA_HEADING_IMAGE'); ?>
								</th>
								<th scope="col" class="w-5 d-none d-lg-table-cell text-center">
									<?php echo Text::_('COM_DREKMETA_HEADING_COLOR'); ?>
								</th>
								<th scope="col" class="w-10 text-center">
									<?php echo HTMLHelper::_('searchtools.sort', 'COM_DREKMETA_HEADING_EMBED', 'a.component_embed_enabled', $listDirn, $listOrder); ?>
								</th>
								<th scope="col" class="w-5 text-center">
									<?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
								</th>
								<th scope="col" class="w-5 d-none d-md-table-cell">
									<?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
								</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($this->items as $i => $item) :
							$isGlobal   = (int) $item->menu_item_id === 0;
							$canEdit    = $this->canDo->get('core.edit');
							$canCheckin = $user->authorise('core.manage', 'com_checkin') || (int) $item->checked_out === (int) $user->id || is_null($item->checked_out);
							$canChange  = $this->canDo->get('core.edit.state') && $canCheckin && !$isGlobal;
							$image      = $item->og_image ? MetaHelper::image($item->og_image)['url'] : '';
							$label      = $isGlobal ? Text::_('COM_DREKMETA_GLOBAL_DEFAULTS') : $item->title;
							?>
							<tr class="row<?php echo $i % 2; ?><?php echo $isGlobal ? ' drekmeta-row-global' : ''; ?>">
								<td class="text-center">
									<?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $label); ?>
								</td>
								<th scope="row">
									<?php if ($item->checked_out) : ?>
										<?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'items.', $canCheckin); ?>
									<?php endif; ?>
									<?php if ($canEdit) : ?>
										<a href="<?php echo Route::_('index.php?option=com_drekmeta&task=item.edit&id=' . (int) $item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape($label); ?>">
											<?php echo $this->escape($label); ?>
										</a>
									<?php else : ?>
										<?php echo $this->escape($label); ?>
									<?php endif; ?>
									<?php if ($isGlobal) : ?>
										<span class="badge bg-info ms-1"><?php echo Text::_('COM_DREKMETA_BADGE_GLOBAL'); ?></span>
									<?php endif; ?>
									<?php if ($item->og_title) : ?>
										<div class="small text-muted text-truncate drekmeta-list-sub">og:title: <?php echo $this->escape($item->og_title); ?></div>
									<?php endif; ?>
								</th>
								<td class="d-none d-md-table-cell">
									<?php if ($isGlobal) : ?>
										<span class="text-muted"><?php echo Text::_('COM_DREKMETA_ALL_PAGES'); ?></span>
									<?php elseif ($item->menu_title === null) : ?>
										<span class="badge bg-danger"><?php echo Text::_('COM_DREKMETA_MENU_ITEM_MISSING'); ?></span>
										<span class="small text-muted">#<?php echo (int) $item->menu_item_id; ?></span>
									<?php else : ?>
										<div>
											<?php echo $this->escape($item->menu_title); ?>
											<?php if ((int) $item->menu_published !== 1) : ?>
												<span class="badge bg-warning text-dark"><?php echo Text::_('JUNPUBLISHED'); ?></span>
											<?php endif; ?>
										</div>
										<div class="small text-muted">
											<?php echo Text::_('COM_DREKMETA_MENU_LABEL'); ?>: <?php echo $this->escape($item->menu_type_title); ?>
										</div>
										<code class="small text-truncate d-inline-block drekmeta-list-link"><?php echo $this->escape($item->menu_link); ?></code>
									<?php endif; ?>
								</td>
								<td class="d-none d-lg-table-cell text-center">
									<?php if ($image) : ?>
										<img src="<?php echo $this->escape($image); ?>" alt="" class="drekmeta-thumb" loading="lazy">
									<?php else : ?>
										<span class="text-muted">&ndash;</span>
									<?php endif; ?>
								</td>
								<td class="d-none d-lg-table-cell text-center">
									<?php if ($item->theme_color) : ?>
										<span class="drekmeta-swatch" style="background-color: <?php echo $this->escape($item->theme_color); ?>" title="<?php echo $this->escape($item->theme_color); ?>"></span>
									<?php else : ?>
										<span class="text-muted">&ndash;</span>
									<?php endif; ?>
								</td>
								<td class="text-center">
									<?php if ((int) $item->component_embed_enabled) : ?>
										<span class="badge bg-success"><?php echo Text::_('COM_DREKMETA_EMBED_ON'); ?></span>
									<?php else : ?>
										<span class="badge bg-secondary"><?php echo Text::_('COM_DREKMETA_EMBED_OFF'); ?></span>
									<?php endif; ?>
								</td>
								<td class="text-center">
									<?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'items.', $canChange); ?>
								</td>
								<td class="d-none d-md-table-cell">
									<?php echo (int) $item->id; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<?php echo $this->pagination->getListFooter(); ?>
				<?php endif; ?>

				<input type="hidden" name="task" value="">
				<input type="hidden" name="boxchecked" value="0">
				<?php echo HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
