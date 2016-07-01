# Adyax Test #

Drupal 8 module with simple REST implementation.

## DO NOT USE IN PRODUCTION ##

Module does NOT contain:

 * access/permissions check
 * csrf token for post requests
 * integration with any other modules like REST or Services
 * dynamically adding of new fields that added through admin ui or code, it locked only to title, type and body
 * complex data validation, it uses default node mechanism `$node->validate()`  
