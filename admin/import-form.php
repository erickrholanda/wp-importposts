<?php $content = self::get_file_content(); ?>
<div class="wrap">
    <h1 class="wp-heading-inline">Importar Posts</h1>
    <hr class="wp-header-end">
    <?php ImportPost::display_messages(); ?>
    <form action="<?php print ImportPost::$plugin_url; ?>" id="posts-filter" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="file" value="<?php print ImportPost::$file['file']; ?>" />
        <input type="hidden" name="url" value="<?php print ImportPost::$file['url']; ?>" />
        <input type="hidden" name="type" value="<?php print ImportPost::$file['type']; ?>" />
        <input type="hidden" name="reset" value="<?php print ImportPost::$reset; ?>" />
        <input type="hidden" name="post_type" value="<?php print ImportPost::$post_type; ?>" />
        <table>
            <tbody>
                <tr>
                    <td colspan="2">Serão importadas <?php echo count($content); ?> linhas.</td>
                </tr>
                <?php if (!empty($content)): ?>
                    <?php foreach($content[0] as $num => $column): ?>
                        <tr>
                            <td>Coluna <?php echo $num+1; ?></td>
                            <td>
                                <select name="column[<?php echo $num; ?>][field_type]">
                                    <option value="post_field"><?php __('Title'); ?></option>
                                    <option value="meta_input"><?php __('Body'); ?></option>
                                    <option value="tax_input"><?php __('Body'); ?></option>
                                </select>
                            </td>
                            <td>
                                <select name="column[<?php echo $num; ?>][field_name]">
                                    <option value="post_title"><?php __('Title'); ?></option>
                                    <option value="post_content"><?php __('Body'); ?></option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>