FROM python:3.7.3-slim

RUN apt-get update && \
    apt-get install -y git make gcc libssl-dev libgmp-dev python-dev libxml2-dev libxslt1-dev zlib1g-dev

RUN pip3 install morphenepython pymongo apscheduler

# COPY . /src

CMD ["python", "/src/history.py"]
