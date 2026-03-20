using System;
using System.IO;
using System.Linq;
using Mono.Cecil;
using ICSharpCode.Decompiler;
using ICSharpCode.Decompiler.CSharp;
using ICSharpCode.Decompiler.TypeSystem;

/// <summary>
/// 1) Структура: InspectDll.exe "path\to\file.dll"
/// 2) Декомпиляция в C#: InspectDll.exe "path\to\file.dll" "output_folder"
/// </summary>
class Program
{
    static int Main(string[] args)
    {
        string path = args.Length > 0 ? args[0] : null;
        if (string.IsNullOrEmpty(path) || !File.Exists(path))
        {
            Console.WriteLine("InspectDll.exe <path-to-dll> [output-folder]");
            Console.WriteLine("  Без папки — вывод структуры. С папкой — декомпиляция в C#.");
            return 1;
        }

        path = Path.GetFullPath(path);
        string outDir = args.Length > 1 ? Path.GetFullPath(args[1]) : null;

        if (!string.IsNullOrEmpty(outDir))
        {
            return DecompileToCSharp(path, outDir);
        }

        return ListStructure(path);
    }

    static int ListStructure(string path)
    {
        try
        {
            var reader = new DefaultAssemblyResolver();
            reader.AddSearchDirectory(Path.GetDirectoryName(path));
            var asm = AssemblyDefinition.ReadAssembly(path, new ReaderParameters { AssemblyResolver = reader });
            Console.WriteLine("Assembly: " + asm.FullName);
            Console.WriteLine();
            foreach (var type in asm.MainModule.Types)
            {
                if (type.IsNested) continue;
                if (type.Name == "<Module>") continue;
                Console.WriteLine("TYPE: " + type.FullName);
                foreach (var method in type.Methods)
                {
                    if (method.IsConstructor || method.IsSetter || method.IsGetter) continue;
                    var ps = string.Join(", ", method.Parameters.Select(p => p.ParameterType + " " + p.Name));
                    Console.WriteLine("  " + method.ReturnType + " " + method.Name + "(" + ps + ")");
                }
                foreach (var field in type.Fields)
                    Console.WriteLine("  [field] " + field.FieldType + " " + field.Name);
                Console.WriteLine();
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine("Error: " + ex.Message);
            return 1;
        }
        return 0;
    }

    static int DecompileToCSharp(string dllPath, string outputDir)
    {
        try
        {
            var settings = new DecompilerSettings
            {
                LoadInMemory = true,
                ThrowOnAssemblyResolveErrors = false
            };
            var decompiler = new CSharpDecompiler(dllPath, settings);
            string fullSource = decompiler.DecompileWholeModuleAsString();
            Directory.CreateDirectory(outputDir);
            string mainFile = Path.Combine(outputDir, "RustDemoPro_Decompiled.cs");
            File.WriteAllText(mainFile, fullSource);
            Console.WriteLine("Декомпилировано в: " + mainFile);
        }
        catch (Exception ex)
        {
            Console.WriteLine("Decompile error: " + ex.Message);
            Console.WriteLine(ex.StackTrace);
            return 1;
        }
        return 0;
    }
}
