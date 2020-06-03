<p><?php echo esc_html_X('While connecting with Run My Accounts API the following error occurred:', 'error email body', 'rma-wc') ?></p>
<table border="1">
    <tr>
        <th><?php echo esc_html_x('Mode', 'error email body', 'rma-wc') ?></th>
        <th><?php echo esc_html_x('Section', 'error email body', 'rma-wc') ?></th>
        <th><?php echo esc_html_x('Section ID', 'error email body', 'rma-wc') ?></th>
        <th><?php echo esc_html_x('Message', 'error email body', 'rma-wc') ?></th>
    </tr>
    <tr>
        <td><?php echo $values['mode']; ?></td>
        <td><?php echo $values['section']; ?></td>
        <td><?php echo $values['section_id']; ?></td>
        <td><?php echo $values['message']; ?></td>
    </tr>
</table>
