{* This template file utilizes system's bootstrap and language translation functionality *}
<h3>{lang key='domainDnsSec.management'} - <strong>{$domain}</strong></h3>

<div class="alert alert-info text-center">
    {lang key='domainDnsSec.warning'}
</div>

{if $successful}
  <div class="alert alert-success text-center">
      {lang key='changessavedsuccessfully'}
  </div>
{/if}

{if $error}
  <div class="alert alert-danger text-center">{$error}</div>
{/if}

<h4>{lang key='domainDnsSec.dsRecords'}</h4>
<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <tr>
        <th style="width:100px;;white-space: nowrap;">{lang key='domainDnsSec.keyTag'}</th>
        <th style="width:100px;">{lang key='domainDnsSec.algorithm'}</th>
        <th style="width:100px;white-space: nowrap;">{lang key='domainDnsSec.digestType'}</th>
        <th>{lang key='domainDnsSec.digest'}</th>
      </tr>
    </thead>
    <tbody>
        {foreach item=ds from=$dsdata name=dsdata}
          <tr>
            <td>{$ds.keyTag}</td>
            <td>{$algOptions[$ds.alg]}</td>
            <td>{$digestOptions[$ds.digestType]}</td>
            <td style="word-break: break-all;">{$ds.digest}</td>
          </tr>
            {foreachelse}
          <tr>
            <td colspan="4">No records</td>
          </tr>
        {/foreach}
    </tbody>
  </table>
</div>

<h4>{lang key='domainDnsSec.keyRecords'}</h4>
<form method="POST" action="">
  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th style="width:170px;">{lang key='domainDnsSec.flags'}</th>
          <th style="width:200px;">{lang key='domainDnsSec.algorithm'}</th>
          <th>{lang key='domainDnsSec.publicKey'}</th>
        </tr>
      </thead>
      <tbody>
          {foreach item=key from=$ksdata name=ksdata}
            <tr>
              <td>
                <select name="DNSSEC[{$smarty.foreach.ksdata.index}][flag]" class="form-control">
                    {foreach $flagOptions as $flag => $name}
                      <option value="{$flag}"{if $key.flag eq $flag} selected{/if}>{$name}</option>
                    {/foreach}
                </select>
              </td>
              <td>
                <select name="DNSSEC[{$smarty.foreach.ksdata.index}][alg]" class="form-control">
                    {foreach $algOptions as $alg => $name}
                      <option value="{$alg}"{if $key.alg eq $alg} selected{/if}>{$name}</option>
                    {/foreach}
                </select>
              </td>
              <td>
                <textarea class="form-control" rows="2" name="DNSSEC[{$smarty.foreach.ksdata.index}][pubKey]">{$key.pubKey}</textarea>
              </td>
            </tr>
          {/foreach}
        <tr>
          <td>
            <select name="DNSSEC[{$smarty.foreach.ksdata.index+1}][flag]" class="form-control">
                {foreach $flagOptions as $flag => $name}
                  <option value="{$flag}">{$name}</option>
                {/foreach}
            </select>
          </td>
          <td>
            <select name="DNSSEC[{$smarty.foreach.ksdata.index+1}][alg]" class="form-control">
                {foreach $algOptions as $alg => $name}
                  <option value="{$alg}">{$name}</option>
                {/foreach}
            </select>
          </td>
          <td>
            <textarea class="form-control" rows="2" name="DNSSEC[{$smarty.foreach.ksdata.index+1}][pubKey]"></textarea>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <p class="text-center">
    <input class="btn btn-large btn-primary" type="submit" name="cmdPublish" value="Publish">
    &nbsp;&nbsp;&nbsp;&nbsp;
    <input class="btn btn-large btn-danger" type="submit" name="cmdUnpublish" value="Unpublish">
    &nbsp;&nbsp;&nbsp;&nbsp;
    <input class="btn btn-large btn-warning" type="submit" name="cmdSign" value="Sign zone">
  </p>
</form>
