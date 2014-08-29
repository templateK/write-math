#!/usr/bin/env python

import os
import logging
import sys
logging.basicConfig(format='%(asctime)s %(levelname)s %(message)s',
                    level=logging.DEBUG,
                    stream=sys.stdout)
import subprocess
import time
import re
# mine
import utils


def get_error_from_logfile(logfile):
    with open(logfile) as f:
        log_content = f.read()
    pattern = re.compile("errors = (\d\.\d+)")
    error = float(pattern.findall(log_content)[-1])
    return error


def test_model(model_folder, basename, test_file):
    model_src = utils.get_latest_working_model(model_folder)
    if model_src is None:
        logging.error("No model with basename '%s' found in '%s'.",
                      basename,
                      model_folder)
    else:
        os.chdir(model_folder)
        time_prefix = time.strftime("%Y-%m-%d-%H-%M")
        logging.info("Evaluate '%s'...", model_src)
        logfile = "%s-testing.log" % time_prefix
        with open(logfile, "w") as log, open(model_src, "r") as model_src_p:
            p = subprocess.Popen(['nntoolkit', 'test', '--batch-size', '1',
                                  test_file],
                                 stdin=model_src_p,
                                 stderr=log)
            ret = p.wait()
            if ret != 0:
                logging.error("nntoolkit finished with ret code %s", str(ret))
                sys.exit()

        return get_error_from_logfile(logfile)


def main(model_folder, run_native=False):
    test_data_path = os.path.join(model_folder, "testdata.pfile")
    error = test_model(model_folder,
                       "model",
                       test_data_path)
    if run_native:
        logging.info("Error: %0.4f", error)
    return error


if __name__ == "__main__":
    PROJECT_ROOT = utils.get_project_root()

    # Get latest model folder
    models_folder = os.path.join(PROJECT_ROOT, "archive/models")
    latest_model = utils.get_latest_folder(models_folder)

    # Get command line arguments
    from argparse import ArgumentParser, ArgumentDefaultsHelpFormatter
    parser = ArgumentParser(description=__doc__,
                            formatter_class=ArgumentDefaultsHelpFormatter)
    parser.add_argument("-m", "--model",
                        dest="model",
                        help="where is the model folder (with a model.yml)?",
                        metavar="FOLDER",
                        type=lambda x: utils.is_valid_folder(parser, x),
                        default=latest_model)
    args = parser.parse_args()
    main(args.model, run_native=True)
