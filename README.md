This php application to scan web sites and detect technologies.
Detect conditions based on Wappalyzer (https://github.com/ElbertF/Wappalyzer)

In Wappalyler php driver use V8 extension. In phpEngineDetect V8 is not needed.

----

1. Update sites list in batch_index.php (later will moved to separate file)
2. Start batch_index.php (via browser address string)
3.0 Need to update sites list in index.php too (This step will removed later)
3.1 OK. After scan, open index.php via browser and look on results