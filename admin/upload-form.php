<div class="wrap">
    <h1 class="wp-heading-inline">Importar Posts</h1>
    <hr class="wp-header-end">
    <?php ImportPost::display_messages(); ?>
    <form action="<?php print ImportPost::$plugin_url; ?>" id="posts-filter" method="POST" enctype="multipart/form-data" style="width: 100%; max-width: 600px">
        <input type="hidden" name="post_type" value="<?php print ImportPost::$post_type; ?>" />
        <table>
            <tbody>
                <tr>
                    <td colspan="2">
                    <label  for="reset">
                        <input type="checkbox" name="reset" id="reset" />
                        Resetar posts antes de importar
                    </label>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                    <label  for="header">
                        <input type="checkbox" name="header" id="header" />
                        1ª linha é cabecalho
                    </label>
                    </td>
                </tr>
                <tr>
                    <td><label  for="file">Arquivo para importar (Extensões: <?php echo implode(', ', ImportPost::$extensions); ?>)</label></td>
                    <td><input type="file" name="file" id="file" /></td>
                </tr>
                <!-- <tr>
                    <td><label  for="file">Tipo de Post a ser importado</label></td>
                    <td>
                        <select name="post_type">
                            <option value="">Selecione um tipo de Post</option>
                            
                            <?php foreach(get_post_types(array('public'=>true), 'objects')  as $key => $type): ?>
                            <option value="<?php echo $key; ?>" <?php print ImportPost::$post_type == $key? 'selected="selected"':''; ?>><?php echo $type->label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr> -->
            </tbody>
        </table>
        <div class="submit inline-edit-save">
            <input type="submit" name="bulk_edit" id="bulk_edit" class="button button-primary" value="Atualizar">
        </div>
    </form>
    <br class="clear">
</div>