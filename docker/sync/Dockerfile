FROM python:3.7.3-slim

RUN apt-get update && apt-get install -y make gcc libssl-dev

RUN pip3 install morphenepython pymongo

CMD ["python", "/src/sync.py"]
