-- Create syntax for TABLE 'circleci_slack_hash'
CREATE TABLE `circleci_slack_hash` (
                                       `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                                       `hash` text,
                                       `ts` text,
                                       PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'github_slack_users'
CREATE TABLE `github_slack_users` (
                                      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                                      `github_username` varchar(100) DEFAULT NULL,
                                      `slack_id` varchar(100) DEFAULT NULL,
                                      `name` varchar(100) DEFAULT NULL,
                                      PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;