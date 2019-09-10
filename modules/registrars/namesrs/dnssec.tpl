<h3 style="margin-bottom:25px;">DNSSEC Management</h3>

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

{if $status == 0}
	<div class="alert alert-danger text-center">
    <p>DNSSEC is not active</p>
	</div>
{/if}

{if $status == 1}
	<div class="alert alert-info text-center">
    <p>DNSSEC is active</p>
	</div>
{/if}

{if $status == 2}
	<div class="alert alert-warning text-center">
    <p>DNSSEC is pending (up to 48 hrs)</p>
	</div>
{/if}

<form method="POST" action="{$smarty.server.PHP_SELF}">
<input type="hidden" name="action" value="domaindetails">
<input type="hidden" name="id" value="{$domainid}">
<input type="hidden" name="modop" value="custom">
<input type="hidden" name="a" value="dnssec">

<table class="table table-striped">
	<tbody>
    <tr>
      <th style="width:100px;">Key Tag</th>
			<td><input class="form-control" type="text" name="dnskey"></td>
		</tr>
		<tr>
      <th style="width:100px;">Flags</th>
			<td><select class="form-control" name="flags">
				<option value="256">ZSK</option>
				<option value="257">KSK</option>
			</select></td>
		</tr>
		<tr>
    	<th style="width:100px;">Algorithm</th>
			<td><select class="form-control" name="alg">
				<option value="5">RSA/SHA-1</option>
				<option value="7">RSASHA1-NSEC3-SHA1</option>
				<option value="8">RSA/SHA-256</option>
				<option value="10">RSA/SHA-512</option>
				<option value="12">GOST R 34.10-2001</option>
				<option value="13">ECDSA/SHA-256</option>
				<option value="14">ECDSA/SHA-384</option>
			</select></td>
		</tr>
	</tbody>
</table>

<p class="text-center">
  <input class="btn btn-large btn-primary" type="submit" name="cmdPublish" value="Publish">
  &nbsp;&nbsp;&nbsp;&nbsp;
  <input class="btn btn-large btn-warning" type="submit" name="cmdUnpublish" value="Unpublish">
</p>

</form>
