#!/usr/bin/env python

from __future__ import print_function
import MySQLdb
import MySQLdb.cursors
from dbconfig import mysql_local
import sys


def query_yes_no(question, default="yes"):
    """Ask a yes/no question via raw_input() and return their answer.

    "question" is a string that is presented to the user.
    "default" is the presumed answer if the user just hits <Enter>.
        It must be "yes" (the default), "no" or None (meaning
        an answer is required of the user).

    The "answer" return value is one of "yes" or "no".
    """
    valid = {"yes": True, "y": True, "ye": True,
             "no": False, "n": False}
    if default is None:
        prompt = " [y/n] "
    elif default == "yes":
        prompt = " [Y/n] "
    elif default == "no":
        prompt = " [y/N] "
    else:
        raise ValueError("invalid default answer: '%s'" % default)

    while True:
        sys.stdout.write(question + prompt)
        choice = raw_input().lower()
        if default is not None and choice == '':
            return valid[default]
        elif choice in valid:
            return valid[choice]
        else:
            sys.stdout.write("Please respond with 'yes' or 'no' "
                             "(or 'y' or 'n').\n")


def clean(mysql):
    """Drop all tables from the database. """
    connection = MySQLdb.connect(host="localhost",  # mysql['host'],
                                 user=mysql['user'],
                                 passwd=mysql['passwd'],
                                 db=mysql['db'],
                                 cursorclass=MySQLdb.cursors.DictCursor)
    cursor = connection.cursor()

    sql = ("SET foreign_key_checks = 0;DROP TABLE `wm_challenges`, "
           "`wm_dtw_worker_data`, `wm_flags`, `wm_formula`, "
           "`wm_formula2challenge`, `wm_formula_in_paper`, "
           "`wm_formula_svg_missing`, `wm_invalid_formula_requests`, "
           "`wm_languages`, `wm_papers`, `wm_raw_data2formula`, "
           "`wm_raw_draw_data`, `wm_renderings`, `wm_similarity`, "
           "`wm_users`, `wm_user_unknown_formula`, `wm_votes`, "
           "`wm_workers`, `wm_worker_answers`;SET foreign_key_checks = 1;")
    a = cursor.execute(sql)
    print(a)

if __name__ == '__main__':
    if query_yes_no("Do you want to remove all content from the database? "
                    "This step cannot be undone!", "no"):
        clean(mysql_local)
