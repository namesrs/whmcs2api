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
<input type="hidden" name="modop" value="custom">
<input type="hidden" name="a" value="setContactDetails">

<table class="table table-striped">
	<tbody>
    <tr>
      <td align="right"><b>ID number</b></td>
			<td><input class="form-control" type="text" name="orgnr" value="{$org_num}"></td>
		</tr>
    <tr>
      <td align="right"><b>Organization</b></td>
			<td><input class="form-control" type="text" name="orgname" value="{$org_name}"></td>
		</tr>
    <tr>
      <td align="right"><b>First name</b></td>
			<td><input class="form-control" type="text" name="firstname" value="{$first_name}"></td>
		</tr>
    <tr>
      <td align="right"><b>Last name</b></td>
			<td><input class="form-control" type="text" name="lastname" value="{$last_name}"></td>
		</tr>
    <tr>
      <td align="right"><b>Country</b></td>
      <td>{$country}</td>
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
			<td><input class="form-control" type="tel" name="phone" value="{$phone}"></td>
		</tr>
    <tr>
      <td align="right"><b>E-mail</b></td>
			<td><input class="form-control" type="email" name="email" value="{$email}"></td>
		</tr>
	</tbody>
</table>

<p class="text-center">
  <input class="btn btn-large btn-primary" type="submit" name="cmdSave" value="{$LANG.clientareasavechanges}">
</p>

</form>
<script language="JavaScript" type="text/javascript">{$redirect}</script>
