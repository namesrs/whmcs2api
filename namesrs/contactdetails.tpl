<h3 style="margin-bottom:25px;">Registrant details</h3>

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
<input type="hidden" name="contactid" value="{$cid}">
<input type="hidden" name="modop" value="custom">
<input type="hidden" name="a" value="contact_details">

<table class="table table-striped">
	<tbody>
    <tr>
      <td align="right"><b>ID number</b></td>
			<td><input class="form-control" type="text" name="orgnr" value="{$org_num}" disabled></td>
		</tr>
    <tr>
      <td align="right"><b>Organization</b></td>
			<td><input class="form-control" type="text" name="orgname" value="{$org_name}" disabled></td>
		</tr>
    <tr>
      <td align="right"><b>First name</b></td>
			<td><input class="form-control" type="text" name="firstname" value="{$first_name}" disabled></td>
		</tr>
    <tr>
      <td align="right"><b>Last name</b></td>
			<td><input class="form-control" type="text" name="lastname" value="{$last_name}" disabled></td>
		</tr>
    <tr>
      <td align="right"><b>Country code</b></td>
			<td><input class="form-control" type="text" name="countrycode" value="{$country}" disabled></td>
		</tr>
    <tr>
      <td align="right"><b>City</b></td>
			<td><input class="form-control" type="text" name="city" value="{$city}"></td>
		</tr>
    <tr>
      <td align="right"><b>ZIP code</b></td>
			<td><input class="form-control" type="text" name="zip" value="{$zip}"></td>
		</tr>
    <tr>
      <td align="right"><b>Address</b></td>
			<td><input class="form-control" type="text" name="address" value="{$address}"></td>
		</tr>
    <tr>
      <td align="right"><b>Phone</b></td>
			<td><input class="form-control" type="text" name="phone" value="{$phone}"></td>
		</tr>
    <tr>
      <td align="right"><b>E-mail</b></td>
			<td><input class="form-control" type="text" name="email" value="{$email}"></td>
		</tr>
	</tbody>
</table>

<p class="text-center">
  <input class="btn btn-large btn-primary" type="submit" name="cmdSave" value="{$LANG.clientareasavechanges}">
</p>

</form>
