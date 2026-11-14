"""Builder-only boundary regression; no network or external XML inputs."""
import pathlib
import subprocess
import sys
import tempfile

headers = """#include <assert.h>
#include <string.h>
#include <libxml/tree.h>
"""
harness = r"""
int main(void) {
    xmlNode node = {0};
    node.type = XML_COMMENT_NODE;
    /* Physical guard byte after a one-byte logical destination. */
    char buf[2] = {0, 90};
    xmlSnprintfElements(buf, 1, &node, 1);
    return buf[0] != 0 || buf[1] != 90;
}
"""
with tempfile.TemporaryDirectory() as tmp:
    for source, expected in zip(sys.argv[1:], [1, 0], strict=True):
        text = pathlib.Path(source).read_text()
        start = text.index("static void\nxmlSnprintfElements(")
        end = text.index("\n}\n", start) + 3
        code = pathlib.Path(tmp) / "check.c"
        binary = pathlib.Path(tmp) / "check"
        code.write_text(headers + text[start:end] + harness)
        subprocess.run(["cc", "-Iinclude", str(code), "-L.libs", "-lxml2", "-o", str(binary)], check=True)
        result = subprocess.run([str(binary)], env={"LD_LIBRARY_PATH": str(pathlib.Path(".libs").resolve())})
        if result.returncode != expected:
            raise SystemExit(f"Boundary control failed: {source}: {result.returncode}, expected {expected}")
print("PASS: original bounds control fails; patched bounds control passes.")
