<?php
require_once "init.php";

$people = array();
$orphans = array();
$everyone = array();

$tree = new MozillaTreeAdapter($everyone);
$auth = new MozillaAuthAdapter();

$data = array();
foreach ($tree->conf as $conf) {
    $search = ldap_search(
        $ldapconn,
        $conf["ldap_search_base"],
        $conf["ldap_search_filter"],
        $conf["ldap_search_attributes"]
    );
    $data = array_merge($data, ldap_get_entries($ldapconn, $search));
}

$tree_view_roots = $tree->roots;
foreach ($data as $person) {
  $mail = $person['mail'][0];
  $everyone[$mail] = $tree->process_entry($person);

  // If a user has a manager, try to find their place in the tree.
  // Unless they're a root themselves.
  if (!in_array($mail, $tree_view_roots) && !empty($person["manager"][0])) {
    $manager = $auth->dn_to_email($person["manager"][0]);
    if (empty($people[$manager])) {
      $people[$manager] = array($mail);
    } else {
      $people[$manager][] = $mail;
    }
  } elseif (!empty($mail) && !in_array($mail, $tree_view_roots)) {
    // Person is an orphan.
    $orphans[] = $mail;
  }
}
$managers = array_keys($people);
$visible_managers = array();

function make_tree($level, $root, $nodes=NULL) {
  global $people;
  global $everyone;
  global $tree;
  global $managers;
  global $visible_managers;

  print "\n". $tree->format_item($everyone, $root, ($nodes == NULL));

  if ($nodes !== NULL && in_array($root, $managers)) {
    $visible_managers[] = $root;
  }

  if (is_array($nodes)) {
    print "\n<ul>";
    usort($nodes, array($tree, "sort_items"));
    foreach ($nodes as $node) {
      if (!empty($people[$node])) {
        make_tree($level + 1, $node, $people[$node]);
      } else {
        make_tree($level + 1, $node);
      }
    }
    print "\n</ul>";
  }
}

require_once "templates/header.php";
?>

<div id="page">

<div id="orgchart" class="tree">
<ul>
<?php
  foreach ($tree_view_roots as $root) {
    if (!isset($people[$root])) {
      make_tree(0, $root);
    } else {
      make_tree(0, $root, $people[$root]);
    }
  }
  $invisible_managers = array_values(array_diff($managers, $visible_managers));
?>
</ul>
</div>
<br />
<div id="orphans" class="tree">
<ul>
  <li class="hr-node collapsed">People who need to set their manager</li>
  <ul style="display:none">
<?php
foreach ($orphans as $orphan) {
  print "\n". $tree->format_item($everyone, $orphan, true);
}
$invisible_people = array();
foreach ($invisible_managers as $invisible_manager) {
  foreach ($people[$invisible_manager] as $invisible_person) {
    $invisible_people[] = $invisible_person;
  }
}
foreach (array_unique($invisible_people) as $invisible_person) {
  print "\n". $tree->format_item($everyone, $invisible_person, TRUE);
}
?>
  </ul>
</ul>
</div>

<div id="person">
</div>

</div>

<script type="text/javascript" src="js/view-tree.js"></script>

<?php require_once "templates/footer.php"; ?>
