<?php

class TCATAuth {

    public function isAdmin() {

        $denied = (defined("ADMIN_USER") && ADMIN_USER != "" && (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] != ADMIN_USER));

        if ($denied) { return false; } else { return true; }

    }

    public function getUserName() {

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
        } elseif (isset($_SERVER['REMOTE_USER'])) {
            $username = $_SERVER['REMOTE_USER'];
        } else {
            $username = null;
        }

        return $username;
    
    }

    public function canAdministrate($bin) {

        if ($this->isAdmin()) return true;

        $username = $this->getUserName();
        if ($username == null) {
            // A null username means authentication is disabled. Default to grant access.
            return true;
        }

        $dbh = pdo_connect();
        $sql = "select administrate from tcat_acl_bins A, tcat_query_bins B where B.querybin = :querybin and A.querybin_id = B.id and username = :username";
        $h = $dbh->prepare($sql);
        $h->bindParam(":username", $username, PDO::PARAM_STR);
        $h->bindParam(":querybin", $bin, PDO::PARAM_STR);
        if ($h->execute() && $h->rowCount() > 0) {
            while ($res = $h->fetch()) {
                $administrate = $res['administrate'];
                if ($administrate == 1) {
                    return true;
                }
            }
        }
        $dbh = false;

        return false;
    }

    public function canAnalyze($bin) {

        if ($this->isAdmin()) return true;

        $username = $this->getUserName();
        if ($username == null) {
            // A null username means authentication is disabled. Default to grant access.
            return true;
        }

        if ($username == 'tcat' || $username == 'dmitcat') {
            // For historical purposes, these reserved usernames have read access to all bins
            return true;
        }

        $dbh = pdo_connect();
        $sql = "select `analyze` from tcat_acl_bins A, tcat_query_bins B where B.querybin = :querybin and A.querybin_id = B.id and username = :username";
        $h = $dbh->prepare($sql);
        $h->bindParam(":username", $username, PDO::PARAM_STR);
        $h->bindParam(":querybin", $bin, PDO::PARAM_STR);
        if ($h->execute() && $h->rowCount() > 0) {
            while ($res = $h->fetch()) {
                $analyze = $res['analyze'];
                if ($analyze == 1) {
                    return true;
                }
            }
        }
        $dbh = false;

        return false;
    }

}
