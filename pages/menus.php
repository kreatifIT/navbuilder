<?php

$id = rex_request('id', 'int');
$func = rex_request('func', 'string');

$nav = rex_post('config', [
    ['name', 'string'],
    ['structure', 'string'],
]);

if ($func === "save") {

    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable("navbuilder_navigation"));

    if ($id > 0) {
        $sql->setWhere('id="' . $id . '"');
        $sql->setValue('name', $nav['name']);
        $sql->setValue('structure', $nav['structure']);
        $sql->update();
        echo rex_view::success('Navigation "' . $nav['name'] . '" wurde aktualisiert');
    } else {
        $sql->setValue('name', $nav['name']);
        $sql->setValue('structure', $nav['structure']);
        $sql->insert();
        $id = (int)$sql->getLastId();
        echo rex_view::success('Navigation "' . $nav['name'] . '" wurde angelegt');
    }
}

if ($func === "delete") {

    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable("navbuilder_navigation"));
    $sql->setWhere('id="' . $id . '"');
    $sql->delete();

    echo rex_view::success('Navigation "' . $nav['name'] . '" wurde gelöscht');
}
if ($func === "copy") {
    $menu         = rex_navbuilder_navigation::query()->select('structure')->where('id', $id)->orderBy('id')->findOne();
    $structure    = $menu->structure;
    $originalName = $menu->name;
    $newName      = $originalName . '_copy';

    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable("navbuilder_navigation"));
    $sql->setValue('name', $newName);
    $sql->setValue('structure', $structure);
    $sql->insert();
    $id = (int) $sql->getLastId();
    echo rex_view::success('Navigation "' . $originalName . '" wurde kopiert und als neues Menü "' . $newName . '" angelegt');
}
if ($func === "createfromstructure") {
    $structure      = [];
    $rootCategories = rex_category::getRootCategories(false);

    // todo: check if has redirect
    foreach ($rootCategories as $idx => $rootCategory) {
        $childCategories = $rootCategory->getChildren(false);
        $childArticles   = $rootCategory->getArticles(false);
        $childItems      = array_merge($childCategories, $childArticles);

        $id   = $rootCategory->getId();
        $name = $rootCategory->getName();
        $type = count($childCategories) || count($childArticles) > 1 ? 'group' : 'intern';

        $structure[$idx] = [
            'text' => $name,
            'href' => $id,
            'type' => $type,
        ];

        if ($type === 'group') {
            $structure[$idx]['children'] = [];
            foreach ($childItems as $childIndex => $childItem) {
                $structure[$idx]['children'][$childIndex] = [
                    'text' => $childItem->getName(),
                    'href' => $childItem->getId(),
                    'type' => 'intern',
                ];
            }
        }
    }

    $navName = 'main_navigation';
    $sql     = rex_sql::factory();
    $sql->setTable(rex::getTable("navbuilder_navigation"));
    $sql->setValue('name', $navName);
    $sql->setValue('structure', json_encode($structure));
    $sql->insert();
    $id = (int) $sql->getLastId();
    echo rex_view::success('Navigation "' . $navName . '" wurde angelegt');
}

if ($func == '' || $func == 'delete') {
    $list = rex_list::factory("SELECT `id`, `name`, CONCAT('REX_NAVBUILDER[name=',`name`,']') as `snippet` FROM `" . rex::getTablePrefix() . "navbuilder_navigation` ORDER BY `name` ASC");
    $list->addTableAttribute('class', 'table-striped');

    // icon column
    $thIcon = '<a href="' . $list->getUrl(['func' => 'add']) . '"><i class="rex-icon rex-icon-add-action"></i></a>';
    $tdIcon = '<i class="rex-icon fa-file-text-o"></i>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'id' => '###id###']);

    $list->setColumnLabel('name', 'Name');
    $list->setColumnLabel('snippet', 'Snippet');

    $list->setColumnParams('name', ['id' => '###id###', 'func' => 'edit']);

    $list->removeColumn('id');

    $content = $list->get();

    $fragment = new rex_fragment();
    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
} else if ($func == 'add' || $func == 'edit' || $func == 'save') {

    $widget = rex_var_link::getWidget('href', 'href', 1);

    $content = '';

    $nav = rex_navbuilder_navigation::create();

    if ($id > 0) {
        $nav = rex_navbuilder_navigation::get($id);
    }

    $buttonProperties = [
        'label' => 'Navigation kopieren',
        'value' => 'copy',
        'icon'  => 'glyphicon glyphicon-copy',
    ];

    if (!$nav->structure && count(rex_category::getRootCategories(false))) {
        $buttonProperties = [
            'label' => 'Navigation aus Kategoriestruktur erstellen',
            'value' => 'createfromstructure',
            'icon'  => 'rex-icon fa-bars',
        ];
    }

    $content .= '
        <script>
			var navbuilderJson = ' . ($nav->structure != '' ? $nav->structure : '{}') . ';
        </script>
		<div class="row">
			<form id="frmOut" action="' . rex_url::currentBackendPage() . '" method="post">
				<input type="hidden" name="id" value="' . $id . '"/>
			
				<div class="col-md-12">
					<div class="panel panel-default">
						<div class="panel-heading clearfix"><h5 class="pull-left">Allgemein</h5></div>
						<div class="panel-body" id="cont">
							<div class="form-group">
								<label for="name" class="col-sm-2 control-label">Name</label>
								<div class="col-sm-10">
									<input id="name" type="text" name="config[name]" value="' . $nav->name . '" class="form-control"/>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-6">                            
						<div class="panel panel-default">
							<div class="panel-heading clearfix"><h5 class="pull-left">Struktur</h5></div>
							<div class="panel-body" id="cont">
								<ul id="myEditor" class="sortableLists list-group"></ul>
							</div>
						</div>
						<div class="form-group">
							<button type="submit" name="func" value="save" class="btn btn-success" id="btnOut"><i class="glyphicon glyphicon-ok"></i> Speichern</button>
							<button type="submit" name="func" value="delete" class="btn btn-delete"><i class="glyphicon glyphicon-delete"></i> Löschen</button>
							<button type="submit" name="func" value="' . $buttonProperties['value'] . '" class="btn btn-primary" ><i class="' . $buttonProperties['icon'] . '"></i> ' . $buttonProperties['label'] . '</button>
                        </div>
						<div class="form-group">
							<textarea class="hidden" id="structure" name="config[structure]" class="form-control" cols="50" rows="10"></textarea>
						</div>
				</div>
			</form>
			<div class="col-md-6">
				<form id="frmEdit" class="form-horizontal">
					<div class="row">
						<div class="col-md-12">
							<div class="panel panel-primary">
								<div class="panel-heading">Gruppe</div>
								<div class="panel-body">
									<div class="form-group">
										<label for="groupLabel" class="col-sm-2 control-label">Name</label>
										<div class="col-sm-10">
											<input id="groupLabel" type="text" name="groupLabel" class="form-control"/>
										</div>
									</div>
								</div>
								<div class="panel-footer">
									<button type="button" id="btnUpdateGroup" class="btn btn-primary"><i class="fa fa-refresh"></i> Aktualisieren</button>
									<button type="button" id="btnAddGroup" class="btn btn-success"><i class="fa fa-plus"></i> Hinzufügen</button>
								</div>
							</div>
						</div>
						<div class="col-md-12">
							<div class="panel panel-primary">
								<div class="panel-heading">Interner Link</div>
								<div class="panel-body">
                                    <div class="form-group">
										<label for="internLabel" class="col-sm-2 control-label">Label</label>
										<div class="col-sm-10">
											<input id="internLabel" type="text" name="internLabel" class="form-control"/>
										</div>
									</div>
									<div class="form-group">
										<label for="internHref" class="col-sm-2 control-label">URL</label>
										<input id="internRealName" type="hidden" name="internRealName"/>
										<div class="col-sm-10">
											' . $widget . '
										</div>
									</div>
									<!--<div class="form-group">
										<label for="target" class="col-sm-2 control-label">Ziel</label>
										<div class="col-sm-10">
											<select name="target" id="target" class="form-control item-nav">
												<option value="_self">Self</option>
												<option value="_blank">Blank</option>
												<option value="_top">Top</option>
											</select>
										</div>
									</div>-->
								</div>
								<div class="panel-footer">
									<button type="button" id="btnUpdateIntern" class="btn btn-primary"><i class="fa fa-refresh"></i> Aktualisieren</button>
									<button type="button" id="btnAddIntern" class="btn btn-success"><i class="fa fa-plus"></i> Hinzufügen</button>
								</div>
							</div>
						</div>
						<div class="col-md-12">
							<div class="panel panel-primary">
								<div class="panel-heading">Externer Link</div>
								<div class="panel-body">
                                    <div class="form-group">
                                        <label for="externLabel" class="col-sm-2 control-label">Label</label>
                                        <div class="col-sm-10">
                                            <input id="externLabel" type="text" name="externLabel" class="form-control"/>
                                        </div>
									</div>
                                    <div class="form-group">
                                        <label for="externHref" class="col-sm-2 control-label">URL</label>
                                        <div class="col-sm-10">
                                            <input id="externHref" type="text" name="externHref" class="form-control"/>
                                        </div>
									</div>
								</div>
								<div class="panel-footer">
									<button type="button" id="btnUpdateExtern" class="btn btn-primary"><i class="fa fa-refresh"></i> Aktualisieren</button>
									<button type="button" id="btnAddExtern" class="btn btn-success"><i class="fa fa-plus"></i> Hinzufügen</button>
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
        ';

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit');
    $fragment->setVar('title', 'Einstellungen');
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');
}
?>