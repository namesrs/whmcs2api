<?php

$additionaldomainfields[".no"] = [
    [
      "Key" => "rules",
      "Id" => 100,
      "Name" => "Rules",
      "Description" => ".NO domains are restricted to legal entities registered in Norway or Norwegian citizens having an address in Norway.",
      "Type" => "display"
    ],
    [
      "Key" => "infos",
      "Id" => 101,
      "Name" => "Additional information",
      "Description" => ".NO domains have different required values depending on the registrant's legal form:
        <ul>
          <li><strong>Legal entity registered in Norway</strong>: provide the company number</li>
          <li><strong>Norwegian citizen being 18+ residing in Norway</strong>: provide the registrant's .NO PID number. If you don't have a PID yet, <a href='https://pid.norid.no/personid/lookup' target='blank'>you can request a PID here</a></li>
        </ul>",
      "Type" => "display"
    ],
    [
      "Key" => "notices",
      "Id" => 102,
      "Name" => "Caution",
      "Description" => "Limited to 5 domain per individual / 100 domains per entity.",
      "Type" => "display"
    ],
    [
      "Key" => "registrant",
      "Id" => 1000,
      "Name" => "Registrant's type",
      "Description" => "",
      "Type" => "dropdown",
      "Required" => true,
      "Options" => "ORG|Legal entity registered in Norway,IND|Norwegian citizen being 18+ residing in Norway",
    ],
    [
      "Key" => "companyNumber",
      "Id" => 1001,
      "Name" => "Legal entity registration number",
      "Description" => "Mandatory for Legal entity registered in Norway",
      "Type" => "text",
      "Required" => [
        "registrant" => ["ORG"]
      ]
    ],
    [
      "Key" => "idNumber",
      "Id" => 1002,
      "Name" => ".NO PID number",
      "Description" => "Mandatory for Norwegian citizen being 18+ residing in Norway. If you don't have a PID yet, <a href='https://pid.norid.no/personid/lookup' target='blank'>you can request a PID here</a>",
      "Type" => "text",
      "Required" => [
        "registrant" => ["IND"]
      ]
    ]
  ];
