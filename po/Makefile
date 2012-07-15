# List here all source files with translatable strings.
POTFILES=$(sort $(shell find ../library -type f -name \*.php))

POFILES=$(wildcard *.po)
MOFILES=$(POFILES:.po=.mo)

all: sysconf.pot $(MOFILES)

install: all
	for file in $(MOFILES); do \
		lang=`echo $$file | sed 's/\.mo//'`; \
		install -d $(prefix)/usr/local/share/locale/$$lang/LC_MESSAGES/; \
		install -m 0644 $$file $(prefix)/usr/local/share/locale/$$lang/LC_MESSAGES/sysconf.mo; \
	done

sysconf.pot: $(POTFILES)
	@echo "Rebuilding the pot file"
	xgettext $(POTFILES) -o sysconf.pot -Lphp

clean:
	rm -f $(MOFILES) messages messages.mo

%.mo: %.po
	msgfmt -o $@ $<

%.po: sysconf.pot
	@echo -n "Merging sysconf.pot and $@"
	@msgmerge $@ sysconf.pot -o $@.new
# Typically all that changes was a date or line number. I'd prefer not to
# commit such changes, so detect and ignore them.
	@if [ "`diff $@ $@.new | grep '[<>]' | grep -v '[<>] #:' | wc -l`" -ne 2 ]; then \
		mv -f $@.new $@; \
	else \
		rm -f $@.new; \
	fi
	@msgfmt --statistics $@

check:
	@for file in $(POFILES); do \
		lang=`echo $$file | sed 's/\.po//'`; \
		printf "$$lang: "; \
		msgfmt -o /dev/null -c -v --statistics $$lang.po;\
	done
