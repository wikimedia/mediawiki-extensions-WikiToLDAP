.SILENT:

DOC: index.pdf

EMACS ?= emacs
LATEX ?= latex

%.pdf: %.org | checkEmacs checkLatex
	${EMACS} -Q -batch --visit=$< --funcall org-latex-export-to-pdf

.PHONY: checkEmacs
checkEmacs:
	output="`${EMACS} --version 2>&1`"														||	(	\
		echo Please install a recent emacs and/or specify the location in the EMACS				&&	\
		echo environment variable \(currently \"${EMACS}\"\) to produce documentation in PDF	&&	\
		echo format.																			&&	\
		echo && echo Output of \"${EMACS} --version\" was: 										&&	\
		echo "$$output"																			&&	\
		exit 1																					)

.PHONY: checkLatex
checkLatex:
	output="`${LATEX} --version 2>&1`"														||	(	\
		echo Please install a recent LaTeX compiler and/or specify the location in the LATEX	&&	\
		echo environment variable \(currently \"${LATEX}\"\) to produce documentation in PDF	&&	\
		echo format.																			&&	\
		echo && echo Output of \"${LATEX} --version\" was: 										&&	\
		echo "$$output"																			&&	\
		exit 1																					)
