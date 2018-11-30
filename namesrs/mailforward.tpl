<h3 style="margin-bottom:25px;">E-mail forwarding</h3>

{if $successful}
	<div class="alert alert-success text-center">
    	<p>{$LANG.changessavedsuccessfully}</p>
	</div>
{/if}

{if $error}
	<div class="alert alert-danger text-center">
    	<p>{$error}</p>
	</div>
{/if}

<form method="POST" action="{$smarty.server.PHP_SELF}">
<input type="hidden" name="action" value="domaindetails">
<input type="hidden" name="id" value="{$domainid}">
<input type="hidden" name="modop" value="custom">
<input type="hidden" name="a" value="SaveEmailForwarding">
<input type="hidden" name="item_del">

<table class="table table-striped">
	<thead>
	    <tr>
	        <th>From address</th>
	        <th>To address</th>
	        <th>Action</th>
		</tr>
	</thead>
	<tbody>
		{foreach item=rec from=$forward name=forward}
		<tr>
			<td>{$rec.from}<input type="hidden" name="item[{$rec.recordid}][from]" value="{$rec.from}"></td>
			<td><input class="form-control" type="text" name="item[{$rec.recordid}][to]" value="{$rec.to}"></td>
			<td align="center"><input class="btn btn-small btn-danger" type="submit" name="cmdRemove" value="X" onClick="javascript:this.form.item_del.value={$rec.recordid}; return true;"></td>
		</tr>
		{/foreach}
		<tr>
			<td><input class="form-control" type="text" name="new_from" style="display: inline-block;width:165pt"> <b>@{$domain}</b></td>
			<td><input class="form-control" type="text" name="new_to" value=""></td>
			<td>&nbsp;</td>
		</tr>
	</tbody>
</table>

<p class="text-center">
    <input class="btn btn-large btn-primary" type="submit" name="cmdSave" value="{$LANG.clientareasavechanges}">
</p>

</form>
