using System;
using System.Collections;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.IO.Compression;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Reflection;
using System.Runtime.CompilerServices;
using System.Runtime.InteropServices;
using System.Runtime.Versioning;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using A;
using ConVar;
using Facepunch;
using HarmonyLib;
using Network;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using UnityEngine;

[assembly: CompilationRelaxations(8)]
[assembly: RuntimeCompatibility(WrapNonExceptionThrows = true)]
[assembly: Debuggable(DebuggableAttribute.DebuggingModes.IgnoreSymbolStoreSequencePoints)]
[assembly: AssemblyTitle("RustDemoPro.HarmonyMod")]
[assembly: AssemblyDescription("")]
[assembly: AssemblyConfiguration("")]
[assembly: AssemblyCompany("")]
[assembly: AssemblyProduct("RustDemoPro.HarmonyMod")]
[assembly: AssemblyCopyright("Copyright ©  2026")]
[assembly: AssemblyTrademark("")]
[assembly: ComVisible(false)]
[assembly: Guid("02f684f2-550c-4c9c-9c87-460ba67e052c")]
[assembly: AssemblyFileVersion("0.2.1.0")]
[assembly: TargetFramework(".NETFramework,Version=v4.8", FrameworkDisplayName = ".NET Framework 4.8")]
[assembly: AssemblyVersion("0.2.1.0")]
namespace A
{
	[CompilerGenerated]
	internal sealed class A<A, a>
	{
		[DebuggerBrowsable(DebuggerBrowsableState.Never)]
		private readonly A m_A;

		[DebuggerBrowsable(DebuggerBrowsableState.Never)]
		private readonly a m_A;

		public A Key => this.A;

		public a Last => this.A;

		[DebuggerHidden]
		public A(A P_0, a P_1)
		{
			this.A = P_0;
			this.A = P_1;
		}

		[DebuggerHidden]
		public override bool Equals(object value)
		{
			A<A, a> a2 = value as A<A, a>;
			if (this != a2)
			{
				if (a2 != null && EqualityComparer<A>.Default.Equals(this.A, a2.A))
				{
					return EqualityComparer<a>.Default.Equals(this.A, a2.A);
				}
				return false;
			}
			return true;
		}

		[DebuggerHidden]
		public override int GetHashCode()
		{
			return (-1634622397 * -1521134295 + EqualityComparer<A>.Default.GetHashCode(this.A)) * -1521134295 + EqualityComparer<a>.Default.GetHashCode(this.A);
		}

		[DebuggerHidden]
		public override string ToString()
		{
			object[] array = new object[2];
			A val = this.A;
			array[0] = ((val != null) ? val.ToString() : null);
			a val2 = this.A;
			array[1] = ((val2 != null) ? val2.ToString() : null);
			return string.Format(null, "{{ Key = {0}, Last = {1} }}", array);
		}
	}
	internal sealed class a
	{
		[CompilerGenerated]
		private sealed class A
		{
			public HashSet<string> A;

			public List<string> A;

			internal void A(string P_0)
			{
				if (!string.IsNullOrEmpty(P_0))
				{
					try
					{
						P_0 = Path.GetFullPath(P_0);
					}
					catch
					{
					}
					if (this.A.Add(P_0))
					{
						this.A.Add(P_0);
					}
				}
			}
		}

		private string m_A;

		private string m_a;

		private string m_B;

		public string A(string P_0 = null)
		{
			try
			{
				return this.m_a ?? (this.m_a = A(a(), P_0));
			}
			catch
			{
				return null;
			}
		}

		public string A(string P_0, string P_1 = null)
		{
			if (string.IsNullOrEmpty(P_0))
			{
				return null;
			}
			string text = (string.IsNullOrWhiteSpace(P_1) ? "outbox" : P_1);
			text = text.Trim().Trim('/', '\\');
			if (string.IsNullOrEmpty(text))
			{
				text = "outbox";
			}
			return Path.Combine(P_0, text);
		}

		public void a(string P_0 = null)
		{
			this.m_A = B();
			this.m_a = A(this.m_A, P_0);
			try
			{
				this.m_B = Path.GetFullPath(this.m_a);
			}
			catch
			{
				this.m_B = this.m_a;
			}
		}

		public bool a(string P_0, string P_1)
		{
			if (string.IsNullOrEmpty(P_0))
			{
				return false;
			}
			if (string.IsNullOrEmpty(P_1) && string.IsNullOrEmpty(this.m_B))
			{
				return false;
			}
			try
			{
				string fullPath = Path.GetFullPath(P_0);
				string fullPath2 = this.m_B;
				if (string.IsNullOrEmpty(fullPath2))
				{
					fullPath2 = Path.GetFullPath(P_1);
				}
				return fullPath.StartsWith(fullPath2, StringComparison.OrdinalIgnoreCase);
			}
			catch
			{
			}
			try
			{
				string value = this.m_B ?? P_1;
				return P_0.IndexOf(value, StringComparison.OrdinalIgnoreCase) >= 0;
			}
			catch
			{
				return false;
			}
		}

		public List<string> A()
		{
			List<string> A2 = new List<string>();
			HashSet<string> A = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
			((Action<string>)delegate(string P_0)
			{
				if (!string.IsNullOrEmpty(P_0))
				{
					try
					{
						P_0 = Path.GetFullPath(P_0);
					}
					catch
					{
					}
					if (A.Add(P_0))
					{
						A2.Add(P_0);
					}
				}
			})("demos");
			return A2;
		}

		public string a()
		{
			return this.m_A ?? (this.m_A = B());
		}

		private string B()
		{
			return "demos";
		}
	}
	internal sealed class M : B
	{
		private readonly Dictionary<ulong, List<J>> m_A = new Dictionary<ulong, List<J>>();

		private readonly Dictionary<ulong, int> m_A = new Dictionary<ulong, int>();

		private readonly object m_A = new object();

		[CompilerGenerated]
		private bool m_A;

		[CompilerGenerated]
		private bool m_a;

		public bool Initialized
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			private set
			{
				this.m_A = value;
			}
		}

		public bool Ready
		{
			[CompilerGenerated]
			get
			{
				return this.m_a;
			}
			[CompilerGenerated]
			private set
			{
				this.m_a = value;
			}
		}

		public void A()
		{
			Initialized = true;
			Ready = true;
		}

		public void A(ulong P_0, J P_1, int P_2)
		{
			if (P_0 == 0L || P_1 == null)
			{
				return;
			}
			lock (this.m_A)
			{
				if (!this.m_A.TryGetValue(P_0, out var value))
				{
					value = new List<J>(256);
					this.m_A[P_0] = value;
				}
				if (P_2 > 0 && value.Count >= P_2)
				{
					this.m_A[P_0] = C(P_0) + 1;
				}
				else
				{
					value.Add(P_1);
				}
			}
		}

		public List<J> A(ulong P_0)
		{
			lock (this.m_A)
			{
				if (this.m_A.TryGetValue(P_0, out var value))
				{
					return value;
				}
			}
			return null;
		}

		public int a(ulong P_0)
		{
			lock (this.m_A)
			{
				return C(P_0);
			}
		}

		public void B(ulong P_0)
		{
			lock (this.m_A)
			{
				if (this.m_A.TryGetValue(P_0, out var value))
				{
					value.Clear();
				}
				this.m_A[P_0] = 0;
			}
		}

		public void b(ulong P_0)
		{
			lock (this.m_A)
			{
				this.m_A.Remove(P_0);
				this.m_A.Remove(P_0);
			}
		}

		private int C(ulong P_0)
		{
			if (!this.m_A.TryGetValue(P_0, out var value))
			{
				return 0;
			}
			return value;
		}
	}
	internal sealed class m : B
	{
		private sealed class A
		{
			[CompilerGenerated]
			private Dictionary<ulong, List<string>> A = new Dictionary<ulong, List<string>>();

			[CompilerGenerated]
			private Dictionary<ulong, List<string>> a = new Dictionary<ulong, List<string>>();

			[CompilerGenerated]
			private string A;

			public Dictionary<ulong, List<string>> NoiseReductionHits
			{
				[CompilerGenerated]
				get
				{
					return this.A;
				}
				[CompilerGenerated]
				set
				{
					this.A = value;
				}
			}

			public Dictionary<ulong, List<string>> PerformanceThresholdHits
			{
				[CompilerGenerated]
				get
				{
					return a;
				}
				[CompilerGenerated]
				set
				{
					a = value;
				}
			}

			public string SavedAtUtc
			{
				[CompilerGenerated]
				get
				{
					return A;
				}
				[CompilerGenerated]
				set
				{
					A = value;
				}
			}
		}

		[Serializable]
		[CompilerGenerated]
		private sealed class a
		{
			public static readonly a A = new a();

			public static Func<KeyValuePair<ulong, List<j>>, KeyValuePair<ulong, List<j>>> A;

			public static Comparison<I> A;

			public static Func<I, bool> A;

			public static Comparison<J> A;

			public static Func<DateTime, bool> A;

			public static Func<DateTime, string> A;

			public static Func<KeyValuePair<ulong, Queue<DateTime>>, A<ulong, DateTime>> A;

			public static Func<A<ulong, DateTime>, DateTime> A;

			internal KeyValuePair<ulong, List<j>> A(KeyValuePair<ulong, List<j>> P_0)
			{
				return new KeyValuePair<ulong, List<j>>(P_0.Key, P_0.Value?.ToList());
			}

			internal int A(I P_0, I P_1)
			{
				return P_0.A.CompareTo(P_1.A);
			}

			internal bool A(I P_0)
			{
				return P_0 != null;
			}

			internal int A(J P_0, J P_1)
			{
				return P_0.chunkOffsetSeconds.CompareTo(P_1.chunkOffsetSeconds);
			}

			internal bool A(DateTime P_0)
			{
				return P_0 != default(DateTime);
			}

			internal string a(DateTime P_0)
			{
				return P_0.ToUniversalTime().ToString("o");
			}

			internal A<ulong, DateTime> A(KeyValuePair<ulong, Queue<DateTime>> P_0)
			{
				return new A<ulong, DateTime>(P_0.Key, P_0.Value?.LastOrDefault() ?? DateTime.MinValue);
			}

			internal DateTime A(A<ulong, DateTime> P_0)
			{
				return P_0.Last;
			}
		}

		[CompilerGenerated]
		private sealed class B
		{
			public BasePlayer A;

			internal string A()
			{
				return this.A?.UserIDString;
			}

			internal string a()
			{
				BasePlayer obj = this.A;
				if (obj == null)
				{
					return null;
				}
				return obj.displayName;
			}
		}

		[CompilerGenerated]
		private sealed class b
		{
			public N.a A;

			internal bool A(I P_0)
			{
				if (P_0 != null)
				{
					return string.Equals(P_0.A, this.A.A, StringComparison.OrdinalIgnoreCase);
				}
				return false;
			}
		}

		[CompilerGenerated]
		private sealed class C
		{
			public DateTime A;

			internal bool A(j P_0)
			{
				if (P_0 != null)
				{
					return P_0.a < this.A - TimeSpan.FromMinutes(15.0);
				}
				return true;
			}
		}

		[CompilerGenerated]
		private sealed class c
		{
			public J A;

			internal bool A(J P_0)
			{
				if (P_0.type == "Report")
				{
					return Math.Abs(P_0.chunkOffsetSeconds - this.A.chunkOffsetSeconds) < 0.01;
				}
				return false;
			}
		}

		[CompilerGenerated]
		private sealed class D
		{
			public J A;

			internal bool A(J P_0)
			{
				if (P_0.type == "Report")
				{
					return Math.Abs(P_0.chunkOffsetSeconds - this.A.chunkOffsetSeconds) < 0.01;
				}
				return false;
			}
		}

		[CompilerGenerated]
		private sealed class d
		{
			public string A;

			internal bool A(BasePlayer P_0)
			{
				if ((Object)(object)P_0 != (Object)null)
				{
					return string.Equals(P_0.displayName, this.A, StringComparison.OrdinalIgnoreCase);
				}
				return false;
			}

			internal bool a(BasePlayer P_0)
			{
				if ((Object)(object)P_0 != (Object)null)
				{
					return string.Equals(P_0.displayName, this.A, StringComparison.OrdinalIgnoreCase);
				}
				return false;
			}
		}

		private const int m_A = 15;

		private const int m_a = 2000;

		private const int m_B = 3;

		private const float m_A = 30f;

		private const string m_A = "HarmonyMods_Data/RustDemoPro/ReportThresholdState.json";

		private const int m_b = 5000;

		private static readonly Dictionary<Type, MethodInfo> m_A = new Dictionary<Type, MethodInfo>();

		private static readonly Dictionary<Type, MethodInfo> m_a = new Dictionary<Type, MethodInfo>();

		private static readonly object m_A = new object();

		private static readonly TimeSpan m_A = TimeSpan.FromMinutes(30.0);

		private static readonly TimeSpan m_a = TimeSpan.FromMinutes(15.0);

		private static readonly TimeSpan m_B = TimeSpan.FromSeconds(30.0);

		private static readonly object m_a = new object();

		private static readonly UTF8Encoding m_A = new UTF8Encoding(encoderShouldEmitUTF8Identifier: false);

		private static readonly JsonSerializer m_A = JsonSerializer.Create(new JsonSerializerSettings
		{
			NullValueHandling = (NullValueHandling)1,
			DefaultValueHandling = (DefaultValueHandling)1
		});

		private readonly N m_A;

		private readonly M m_A;

		private readonly O m_A;

		private readonly Dictionary<ulong, List<I>> m_A = new Dictionary<ulong, List<I>>();

		private readonly Dictionary<ulong, List<j>> m_A = new Dictionary<ulong, List<j>>();

		private readonly Dictionary<ulong, DateTime> m_A = new Dictionary<ulong, DateTime>();

		private readonly Queue<L> m_A = new Queue<L>();

		private readonly object m_B = new object();

		private readonly MonoBehaviour m_A;

		private Dictionary<string, bool> m_A;

		private Dictionary<ulong, Queue<DateTime>> m_A = new Dictionary<ulong, Queue<DateTime>>();

		private Dictionary<ulong, Queue<DateTime>> m_a = new Dictionary<ulong, Queue<DateTime>>();

		private bool m_A = true;

		private Coroutine m_A;

		private Coroutine m_a;

		private bool m_a;

		private bool m_B;

		private int m_C = 1;

		private TimeSpan m_b = TimeSpan.Zero;

		private bool m_b;

		private int m_c = 1;

		private TimeSpan m_C = TimeSpan.Zero;

		private bool m_C;

		[CompilerGenerated]
		private bool m_c;

		[CompilerGenerated]
		private bool m_D;

		public bool Initialized
		{
			[CompilerGenerated]
			get
			{
				return this.m_c;
			}
			[CompilerGenerated]
			private set
			{
				this.m_c = value;
			}
		}

		public bool Ready
		{
			[CompilerGenerated]
			get
			{
				return this.m_D;
			}
			[CompilerGenerated]
			private set
			{
				this.m_D = value;
			}
		}

		public m(N P_0, M P_1, O P_2, MonoBehaviour P_3)
		{
			this.m_A = P_0;
			this.m_A = P_1;
			this.m_A = P_2;
			this.m_A = P_3;
			if (this.m_A != null)
			{
				this.m_A.ChunkFinalized += A;
			}
		}

		public void A()
		{
			Initialized = true;
			Ready = true;
			if ((Object)(object)this.m_A != (Object)null && this.m_a == null)
			{
				this.m_a = this.m_A.StartCoroutine(B());
			}
		}

		public void A(bool P_0, Dictionary<string, bool> P_1, bool P_2, int P_3, double P_4, bool P_5, int P_6, double P_7)
		{
			this.m_A = P_0;
			this.m_A = P_1;
			this.m_B = P_2;
			this.m_C = Math.Max(1, P_3);
			this.m_b = TimeSpan.FromMinutes(Math.Max(0.0, P_4));
			this.m_b = P_5;
			this.m_c = Math.Max(1, P_6);
			this.m_C = TimeSpan.FromMinutes(Math.Max(0.0, P_7));
			if (!this.m_C)
			{
				C();
			}
			if (!this.m_B)
			{
				lock (this.m_B)
				{
					this.m_A.Clear();
				}
				c();
			}
			if (!this.m_b)
			{
				lock (this.m_B)
				{
					this.m_a.Clear();
				}
				c();
			}
		}

		public void A(BasePlayer P_0, RPCMessage P_1)
		{
			//IL_003a: Unknown result type (might be due to invalid IL or missing references)
			if (!this.m_A)
			{
				return;
			}
			string text = A(() => P_0?.UserIDString);
			string text2 = A(delegate
			{
				BasePlayer obj = P_0;
				return (obj == null) ? null : obj.displayName;
			});
			NetRead read = P_1.read;
			if (read != null && A(read, out var num))
			{
				string text3 = A(read);
				string text4 = a(read);
				if (text4 != null && text4.Length > 1400)
				{
					text4 = text4.Substring(0, 1400);
				}
				string text5 = A(read);
				string text6 = A(read);
				string text7 = A(read);
				A((object)read, num);
				A("[RustDemoPro] Report parse debug: subject='" + text3 + "' type='" + text5 + "' targetId='" + text6 + "' targetName='" + text7 + "'");
				A(text, text2, text6, text7, text5, text3, text4);
			}
		}

		public void A(string P_0, string P_1, string P_2, string P_3, string P_4, string P_5, string P_6)
		{
			if (!this.m_A)
			{
				A("[RustDemoPro] Incident report skipped: service disabled.");
				return;
			}
			A("[RustDemoPro] Incident got report: targetId='" + P_2 + "' type='" + P_4 + "' subject='" + P_5 + "'");
			string text = b(P_5);
			string text2 = b(P_6);
			string text3 = b(P_4);
			string text4 = b(P_2);
			string text5 = b(P_3);
			if (global::A.b.A(this.m_A, text3, text))
			{
				A("[RustDemoPro] Report dropped by ReportCaptureTypes. type='" + text3 + "' subject='" + text + "'");
			}
			else
			{
				A($"[RustDemoPro] ReportDetailed: type='{text3}' subject='{text}' targetId='{text4}' enabled={this.m_A}");
				string text6 = A(text, text3);
				A(b(P_0), b(P_1), text4, text6, text, text2, text3, text5);
			}
		}

		private static void A(string P_0)
		{
			global::A.C instance = SingletonComponent<global::A.C>.Instance;
			if (instance == null || instance.Config?.Logging?.Debug != true)
			{
				return;
			}
			try
			{
				Debug.Log((object)P_0);
			}
			catch
			{
			}
		}

		private void A(string P_0, string P_1, string P_2, string P_3, string P_4, string P_5, string P_6, string P_7)
		{
			if (!this.m_A)
			{
				return;
			}
			DateTimeOffset dateTimeOffset = D();
			ulong num = a(P_0);
			ulong num2 = a(P_2);
			if (num2 == 0L)
			{
				num2 = B(P_2);
			}
			string value = ((num2 != 0) ? num2.ToString() : null);
			string value2 = ((num != 0) ? num.ToString() : null);
			if (!string.IsNullOrEmpty(value2))
			{
				if (string.Equals(P_3, value2, StringComparison.OrdinalIgnoreCase))
				{
					P_3 = null;
				}
				if (string.Equals(P_4, value2, StringComparison.OrdinalIgnoreCase))
				{
					P_4 = null;
				}
			}
			if (!string.IsNullOrEmpty(value))
			{
				if (string.Equals(P_3, value, StringComparison.OrdinalIgnoreCase))
				{
					P_3 = null;
				}
				if (string.Equals(P_4, value, StringComparison.OrdinalIgnoreCase))
				{
					P_4 = null;
				}
			}
			if (num2 == 0L)
			{
				Debug.LogWarning((object)$"[RustDemoPro] Ignoring report for unknown target '{P_2}' (reporter {num}).");
				return;
			}
			if (string.IsNullOrEmpty(P_1))
			{
				P_1 = b(num);
			}
			string text = ((!string.IsNullOrWhiteSpace(P_7)) ? P_7 : b(num2));
			if (string.IsNullOrEmpty(text))
			{
				text = P_2;
			}
			int num3 = 1;
			if (this.m_b)
			{
				DateTime utcDateTime = dateTimeOffset.UtcDateTime;
				num3 = A(num2, utcDateTime);
				N obj = this.m_A;
				if (obj == null || !obj.a(num2, utcDateTime))
				{
					if (num3 < this.m_c)
					{
						A($"[RustDemoPro] Performance threshold not met for {num2}: {num3}/{this.m_c} in {this.m_C.TotalMinutes:0.#}m.");
						return;
					}
					this.m_A?.A(num2, utcDateTime);
				}
			}
			DateTime dateTime = dateTimeOffset.UtcDateTime - m.m_A;
			DateTime dateTime2 = dateTimeOffset.UtcDateTime + m.m_a;
			DateTimeOffset dateTimeOffset2 = dateTimeOffset.Add(-m.m_A);
			DateTimeOffset dateTimeOffset3 = dateTimeOffset.Add(m.m_a);
			k k2 = new k
			{
				reportId = A(num, num2, dateTimeOffset.UtcDateTime),
				reporterUserId = num,
				reporterSteamId = ((num != 0) ? num.ToString() : null),
				reporterName = P_1,
				reportedUserId = num2,
				reportedSteamId = ((num2 != 0) ? num2.ToString() : null),
				reportedName = text,
				reason = P_3,
				subject = P_4,
				message = P_5,
				type = P_6,
				reportedAtUtc = dateTimeOffset.UtcDateTime.ToString("o"),
				captureWindowStartUtc = dateTime.ToString("o"),
				captureWindowEndUtc = dateTime2.ToString("o"),
				captureWindowBeforeMinutes = (int)Math.Round(m.m_A.TotalMinutes),
				captureWindowAfterMinutes = (int)Math.Round(m.m_a.TotalMinutes),
				reportedAtLocal = a(dateTimeOffset),
				captureWindowStartLocal = a(dateTimeOffset2),
				captureWindowEndLocal = a(dateTimeOffset3),
				reportCount = 1
			};
			A(num2, k2, dateTimeOffset.UtcDateTime);
			int num4 = num3;
			if (!this.m_b && !A(num2, dateTimeOffset.UtcDateTime, out num4))
			{
				A($"[RustDemoPro] Noise reduction threshold not met for {num2}: {num4}/{this.m_C} in {this.m_b.TotalMinutes:0.#}m.");
				return;
			}
			if (this.m_b)
			{
				k2.reportCount = Math.Max(k2.reportCount, num3);
			}
			else
			{
				k2.reportCount = Math.Max(k2.reportCount, num4);
			}
			A(num2, k2, dateTime, dateTime2);
			A(new L
			{
				A = num2,
				A = k2,
				A = dateTime,
				a = dateTime2
			});
		}

		private int A(ulong P_0, DateTime P_1)
		{
			if (!this.m_b || this.m_c <= 1 || this.m_C <= TimeSpan.Zero)
			{
				return 1;
			}
			if (P_0 == 0L)
			{
				return 1;
			}
			int result = 0;
			lock (this.m_B)
			{
				if (!this.m_a.TryGetValue(P_0, out var value) || value == null)
				{
					value = new Queue<DateTime>();
					this.m_a[P_0] = value;
				}
				value.Enqueue(P_1);
				DateTime dateTime = P_1 - this.m_C;
				while (value.Count > 0 && value.Peek() < dateTime)
				{
					value.Dequeue();
				}
				result = value.Count;
				if (value.Count == 0)
				{
					this.m_a.Remove(P_0);
				}
			}
			c();
			return result;
		}

		private bool A(ulong P_0, DateTime P_1, out int P_2)
		{
			P_2 = 0;
			if (!this.m_B || this.m_C <= 1 || this.m_b <= TimeSpan.Zero)
			{
				return true;
			}
			if (P_0 == 0L)
			{
				return true;
			}
			lock (this.m_B)
			{
				if (!this.m_A.TryGetValue(P_0, out var value) || value == null)
				{
					value = new Queue<DateTime>();
					this.m_A[P_0] = value;
				}
				value.Enqueue(P_1);
				DateTime dateTime = P_1 - this.m_b;
				while (value.Count > 0 && value.Peek() < dateTime)
				{
					value.Dequeue();
				}
				P_2 = value.Count;
				if (value.Count == 0)
				{
					this.m_A.Remove(P_0);
				}
			}
			c();
			return P_2 >= this.m_C;
		}

		private void A(L P_0)
		{
			if (P_0 != null)
			{
				lock (this.m_B)
				{
					this.m_A.Enqueue(P_0);
				}
				if (this.m_A == null && (Object)(object)this.m_A != (Object)null)
				{
					this.m_A = this.m_A.StartCoroutine(a());
				}
			}
		}

		private IEnumerator a()
		{
			if (this.m_a)
			{
				yield break;
			}
			this.m_a = true;
			yield return null;
			try
			{
				while (true)
				{
					L l = null;
					lock (this.m_B)
					{
						if (this.m_A.Count == 0)
						{
							break;
						}
						l = this.m_A.Dequeue();
					}
					if (l != null)
					{
						a(l.A, l.A, l.A, l.a);
						a(l.A);
					}
					yield return null;
				}
			}
			finally
			{
				m m2 = this;
				m2.m_a = false;
				m2.m_A = null;
				if ((Object)(object)m2.m_A != (Object)null)
				{
					lock (m2.m_B)
					{
						if (m2.m_A.Count > 0)
						{
							m2.m_A = m2.m_A.StartCoroutine(m2.a());
						}
					}
				}
			}
		}

		private IEnumerator B()
		{
			WaitForSeconds val = new WaitForSeconds(30f);
			while (true)
			{
				try
				{
					b();
				}
				catch
				{
				}
				yield return val;
			}
		}

		private void b()
		{
			if (!this.m_A)
			{
				return;
			}
			List<KeyValuePair<ulong, List<j>>> list = null;
			lock (this.m_B)
			{
				if (this.m_A.Count == 0)
				{
					return;
				}
				list = this.m_A.Select((KeyValuePair<ulong, List<j>> P_0) => new KeyValuePair<ulong, List<j>>(P_0.Key, P_0.Value?.ToList())).ToList();
			}
			foreach (KeyValuePair<ulong, List<j>> item in list)
			{
				ulong key = item.Key;
				List<j> value = item.Value;
				if (value == null || value.Count == 0)
				{
					A(key);
					continue;
				}
				foreach (j item2 in value)
				{
					if (item2?.A != null)
					{
						a(key, item2.A, item2.A, item2.a);
					}
				}
				A(key);
			}
		}

		private void A(N.a P_0)
		{
			if (P_0 == null || P_0.A == 0L || string.IsNullOrEmpty(P_0.A))
			{
				return;
			}
			I i2;
			lock (this.m_B)
			{
				List<I> list = A(P_0.A, true);
				list.RemoveAll((I P_0) => P_0 != null && string.Equals(P_0.A, P_0.A, StringComparison.OrdinalIgnoreCase));
				i2 = new I
				{
					A = P_0.A,
					a = P_0.a,
					B = P_0.B,
					A = P_0.A,
					a = P_0.a
				};
				list.Add(i2);
				list.Sort((I P_0, I P_1) => P_0.A.CompareTo(P_1.A));
			}
			A(P_0.A, i2);
			B(P_0.A);
		}

		private void A(ulong P_0, k P_1, DateTime P_2, DateTime P_3)
		{
			if (P_0 == 0L || P_1 == null)
			{
				return;
			}
			lock (this.m_B)
			{
				if (!this.m_A.TryGetValue(P_0, out var value))
				{
					value = new List<j>();
					this.m_A[P_0] = value;
				}
				value.Add(new j
				{
					A = P_1,
					A = P_2,
					a = P_3
				});
			}
			A(P_0);
		}

		private void A(ulong P_0)
		{
			lock (this.m_B)
			{
				if (this.m_A.TryGetValue(P_0, out var value))
				{
					DateTime A = DateTime.UtcNow;
					value.RemoveAll((j P_0) => P_0 == null || P_0.a < A - TimeSpan.FromMinutes(15.0));
					if (value.Count == 0)
					{
						this.m_A.Remove(P_0);
					}
				}
			}
		}

		private void a(ulong P_0, k P_1, DateTime P_2, DateTime P_3)
		{
			if (P_0 == 0L || P_1 == null)
			{
				return;
			}
			List<I> value;
			lock (this.m_B)
			{
				if (!this.m_A.TryGetValue(P_0, out value) || value == null || value.Count == 0)
				{
					return;
				}
				value = value.ToList();
			}
			foreach (I item in value)
			{
				if (item != null && !item.A && A(item.A, item.a, P_2, P_3))
				{
					A(P_0, item, P_1);
				}
			}
		}

		private void A(ulong P_0, I P_1)
		{
			List<j> value;
			lock (this.m_B)
			{
				if (!this.m_A.TryGetValue(P_0, out value) || value == null)
				{
					return;
				}
				value = value.ToList();
			}
			foreach (j item in value)
			{
				if (item != null && item.A != null && A(P_1.A, P_1.a, item.A, item.a))
				{
					A(P_0, P_1, item.A);
				}
			}
			A(P_0);
		}

		private void A(ulong P_0, k P_1, DateTime P_2)
		{
			if (P_0 == 0L || P_1 == null || this.m_A == null || this.m_A == null || (this.m_b && !this.m_A.a(P_0, P_2)) || !this.m_A.A(P_0, out var a2))
			{
				return;
			}
			J A = this.A(a2.A, P_2, P_1);
			if (A != null)
			{
				List<J> list = this.m_A.A(P_0);
				if (list == null || !list.Any((J P_0) => P_0.type == "Report" && Math.Abs(P_0.chunkOffsetSeconds - A.chunkOffsetSeconds) < 0.01))
				{
					P_1.reportMarkerSeconds = A.chunkOffsetSeconds;
					this.m_A.A(P_0, A, 2000);
				}
			}
		}

		private J A(DateTime P_0, DateTime P_1, k P_2)
		{
			if (P_2 == null)
			{
				return null;
			}
			double totalSeconds = (P_1 - P_0).TotalSeconds;
			DateTimeOffset dateTimeOffset = D();
			return new J
			{
				serverSeconds = A(dateTimeOffset),
				serverTimeLocal = a(dateTimeOffset),
				chunkOffsetSeconds = totalSeconds,
				type = "Report",
				attackerUserId = P_2.reporterUserId,
				attackerName = P_2.reporterName,
				targetUserId = P_2.reportedUserId,
				targetName = P_2.reportedName,
				info = P_2.reason
			};
		}

		private bool A(DateTime P_0, DateTime P_1, DateTime P_2, DateTime P_3)
		{
			if (P_1 >= P_2)
			{
				return P_0 <= P_3;
			}
			return false;
		}

		private void a(ulong P_0)
		{
			if (this.m_A == null || P_0 == 0L)
			{
				return;
			}
			int num = 0;
			lock (this.m_B)
			{
				num = A(P_0, false)?.Count((I P_0) => P_0 != null) ?? 0;
			}
			if (num >= 2)
			{
				return;
			}
			DateTime? dateTime = null;
			lock (this.m_B)
			{
				if (this.m_A.TryGetValue(P_0, out var value))
				{
					dateTime = value;
				}
			}
			if ((dateTime.HasValue && DateTime.UtcNow - dateTime.Value < m.m_B) || !this.m_A.A(P_0, "report-flush"))
			{
				return;
			}
			lock (this.m_B)
			{
				this.m_A[P_0] = DateTime.UtcNow;
			}
		}

		private List<I> A(ulong P_0, bool P_1)
		{
			if (P_0 == 0L)
			{
				return null;
			}
			if (!this.m_A.TryGetValue(P_0, out var value) && P_1)
			{
				value = new List<I>();
				this.m_A[P_0] = value;
			}
			return value;
		}

		private void A(ulong P_0, I P_1, k P_2)
		{
			if (P_1 == null || P_2 == null || P_1.A || !File.Exists(P_1.A))
			{
				return;
			}
			string item = P_2.reportedAtUtc ?? Guid.NewGuid().ToString("N");
			if (!P_1.A.Add(item))
			{
				return;
			}
			A(P_1, P_2);
			O o = this.m_A;
			if (o == null || !o.A(P_1, P_2))
			{
				P_1.A.Remove(item);
				return;
			}
			P_1.A = true;
			lock (this.m_B)
			{
				if (this.m_A.TryGetValue(P_0, out var value))
				{
					value?.Remove(P_1);
				}
			}
		}

		private void A(I P_0, k P_1)
		{
			if (P_0 == null || P_1 == null || string.IsNullOrEmpty(P_0.B))
			{
				return;
			}
			try
			{
				List<J> list = new List<J>();
				if (File.Exists(P_0.B))
				{
					try
					{
						list = JsonConvert.DeserializeObject<List<J>>(File.ReadAllText(P_0.B)) ?? new List<J>();
					}
					catch
					{
						list = new List<J>();
					}
				}
				DateTime dateTime = m.A(P_1.reportedAtUtc, DateTime.UtcNow);
				J A = this.A(P_0.A, dateTime, P_1);
				if (A != null && !list.Any((J P_0) => P_0.type == "Report" && Math.Abs(P_0.chunkOffsetSeconds - A.chunkOffsetSeconds) < 0.01))
				{
					list.Add(A);
				}
				try
				{
					list.Sort((J P_0, J P_1) => P_0.chunkOffsetSeconds.CompareTo(P_1.chunkOffsetSeconds));
				}
				catch
				{
				}
				this.A(P_0.B, list);
			}
			catch
			{
			}
		}

		private void B(ulong P_0)
		{
			if (P_0 == 0L)
			{
				return;
			}
			lock (this.m_B)
			{
				List<I> list = A(P_0, false);
				if (list == null)
				{
					return;
				}
				TimeSpan timeSpan = TimeSpan.FromMinutes(Math.Min(60, Math.Max(45, 45)));
				DateTime utcNow = DateTime.UtcNow;
				foreach (I item in list.ToList())
				{
					if (item != null)
					{
						bool flag = item.a + timeSpan <= utcNow;
						if (!flag && list.Count <= 3)
						{
							break;
						}
						if (flag)
						{
							list.Remove(item);
						}
					}
				}
				if (list.Count == 0)
				{
					this.m_A.Remove(P_0);
				}
			}
		}

		private void A<A>(string P_0, A P_1)
		{
			//IL_0057: Unknown result type (might be due to invalid IL or missing references)
			//IL_005c: Unknown result type (might be due to invalid IL or missing references)
			//IL_0065: Expected O, but got Unknown
			string text = P_0 + ".tmp";
			try
			{
				string directoryName = Path.GetDirectoryName(P_0);
				if (!string.IsNullOrEmpty(directoryName))
				{
					Directory.CreateDirectory(directoryName);
				}
				try
				{
					if (File.Exists(text))
					{
						File.Delete(text);
					}
				}
				catch
				{
				}
				using (FileStream stream = new FileStream(text, FileMode.Create, FileAccess.Write, FileShare.None, 65536))
				{
					using StreamWriter streamWriter = new StreamWriter(stream, m.m_A, 65536);
					JsonTextWriter val = new JsonTextWriter((TextWriter)streamWriter)
					{
						Formatting = (Formatting)0
					};
					try
					{
						lock (m.m_a)
						{
							m.m_A.Serialize((JsonWriter)(object)val, (object)P_1);
						}
					}
					finally
					{
						((IDisposable)val)?.Dispose();
					}
				}
				if (File.Exists(P_0))
				{
					File.Delete(P_0);
				}
				File.Move(text, P_0);
			}
			catch
			{
				try
				{
					if (File.Exists(text))
					{
						File.Delete(text);
					}
				}
				catch
				{
				}
			}
		}

		private void C()
		{
			this.m_C = true;
			try
			{
				if (!File.Exists("HarmonyMods_Data/RustDemoPro/ReportThresholdState.json"))
				{
					return;
				}
				string text = File.ReadAllText("HarmonyMods_Data/RustDemoPro/ReportThresholdState.json");
				if (string.IsNullOrWhiteSpace(text))
				{
					return;
				}
				A a2 = JsonConvert.DeserializeObject<A>(text);
				if (a2 != null)
				{
					lock (this.m_B)
					{
						this.m_A = A(a2.NoiseReductionHits, this.m_b);
						this.m_a = A(a2.PerformanceThresholdHits, this.m_C);
					}
					c();
				}
			}
			catch
			{
			}
		}

		private void c()
		{
			try
			{
				A a2;
				lock (this.m_B)
				{
					A(this.m_A, this.m_b);
					A(this.m_a, this.m_C);
					a2 = new A
					{
						NoiseReductionHits = A(this.m_A),
						PerformanceThresholdHits = A(this.m_a),
						SavedAtUtc = DateTime.UtcNow.ToString("o")
					};
				}
				string directoryName = Path.GetDirectoryName("HarmonyMods_Data/RustDemoPro/ReportThresholdState.json");
				if (!string.IsNullOrEmpty(directoryName))
				{
					Directory.CreateDirectory(directoryName);
				}
				A("HarmonyMods_Data/RustDemoPro/ReportThresholdState.json", a2);
			}
			catch
			{
			}
		}

		private static Dictionary<ulong, Queue<DateTime>> A(Dictionary<ulong, List<string>> P_0, TimeSpan P_1)
		{
			Dictionary<ulong, Queue<DateTime>> dictionary = new Dictionary<ulong, Queue<DateTime>>();
			if (P_0 == null)
			{
				return dictionary;
			}
			DateTime utcNow = DateTime.UtcNow;
			DateTime? dateTime = ((P_1 > TimeSpan.Zero) ? new DateTime?(utcNow - P_1) : null);
			foreach (KeyValuePair<ulong, List<string>> item in P_0)
			{
				if (item.Value == null || item.Value.Count == 0)
				{
					continue;
				}
				Queue<DateTime> queue = new Queue<DateTime>();
				foreach (string item2 in item.Value)
				{
					if (!string.IsNullOrWhiteSpace(item2) && DateTime.TryParse(item2, out var result))
					{
						DateTime dateTime2 = DateTime.SpecifyKind(result, DateTimeKind.Utc);
						if (!dateTime.HasValue || !(dateTime2 < dateTime.Value))
						{
							queue.Enqueue(dateTime2);
						}
					}
				}
				if (queue.Count > 0)
				{
					dictionary[item.Key] = queue;
				}
			}
			return dictionary;
		}

		private static Dictionary<ulong, List<string>> A(Dictionary<ulong, Queue<DateTime>> P_0)
		{
			Dictionary<ulong, List<string>> dictionary = new Dictionary<ulong, List<string>>();
			if (P_0 == null)
			{
				return dictionary;
			}
			foreach (KeyValuePair<ulong, Queue<DateTime>> item in P_0)
			{
				if (item.Value != null && item.Value.Count != 0)
				{
					List<string> list = (from P_0 in item.Value
						where P_0 != default(DateTime)
						select P_0.ToUniversalTime().ToString("o")).ToList();
					if (list.Count > 0)
					{
						dictionary[item.Key] = list;
					}
				}
			}
			return dictionary;
		}

		private static void A(Dictionary<ulong, Queue<DateTime>> P_0, TimeSpan P_1)
		{
			if (P_0 == null)
			{
				return;
			}
			DateTime utcNow = DateTime.UtcNow;
			DateTime? dateTime = ((P_1 > TimeSpan.Zero) ? new DateTime?(utcNow - P_1) : null);
			foreach (ulong item in P_0.Keys.ToList())
			{
				Queue<DateTime> queue = P_0[item];
				if (queue == null)
				{
					P_0.Remove(item);
					continue;
				}
				if (dateTime.HasValue)
				{
					while (queue.Count > 0 && queue.Peek() < dateTime.Value)
					{
						queue.Dequeue();
					}
				}
				else
				{
					queue.Clear();
				}
				if (queue.Count == 0)
				{
					P_0.Remove(item);
				}
			}
			if (P_0.Count > 5000)
			{
				List<A<ulong, DateTime>> list = (from P_0 in P_0
					select new A<ulong, DateTime>(P_0.Key, P_0.Value?.LastOrDefault() ?? DateTime.MinValue) into P_0
					orderby P_0.Last
					select P_0).ToList();
				int num = P_0.Count - 5000;
				for (int l = 0; l < num && l < list.Count; l++)
				{
					P_0.Remove(list[l].Key);
				}
			}
		}

		private string A(string P_0, string P_1)
		{
			if (!string.IsNullOrWhiteSpace(P_0))
			{
				return P_0.Trim();
			}
			if (!string.IsNullOrWhiteSpace(P_1))
			{
				return P_1.Trim();
			}
			return "Reported";
		}

		private ulong a(string P_0)
		{
			if (string.IsNullOrWhiteSpace(P_0))
			{
				return 0uL;
			}
			if (ulong.TryParse(P_0, out var result))
			{
				return result;
			}
			return B(P_0);
		}

		private ulong B(string P_0)
		{
			//IL_0046: Unknown result type (might be due to invalid IL or missing references)
			//IL_0081: Unknown result type (might be due to invalid IL or missing references)
			if (string.IsNullOrEmpty(P_0))
			{
				return 0uL;
			}
			try
			{
				BasePlayer val = ((IEnumerable<BasePlayer>)BasePlayer.activePlayerList)?.FirstOrDefault((BasePlayer P_0) => (Object)(object)P_0 != (Object)null && string.Equals(P_0.displayName, P_0, StringComparison.OrdinalIgnoreCase));
				if ((Object)(object)val != (Object)null)
				{
					return EncryptedValue<ulong>.op_Implicit(val.userID);
				}
			}
			catch
			{
			}
			try
			{
				BasePlayer val2 = ((IEnumerable<BasePlayer>)BasePlayer.sleepingPlayerList)?.FirstOrDefault((BasePlayer P_0) => (Object)(object)P_0 != (Object)null && string.Equals(P_0.displayName, P_0, StringComparison.OrdinalIgnoreCase));
				if ((Object)(object)val2 != (Object)null)
				{
					return EncryptedValue<ulong>.op_Implicit(val2.userID);
				}
			}
			catch
			{
			}
			return 0uL;
		}

		private string b(ulong P_0)
		{
			//IL_0049: Unknown result type (might be due to invalid IL or missing references)
			//IL_004e: Unknown result type (might be due to invalid IL or missing references)
			//IL_0066: Unknown result type (might be due to invalid IL or missing references)
			if (P_0 == 0L)
			{
				return null;
			}
			try
			{
				BasePlayer val = BasePlayer.FindByID(P_0);
				if ((Object)(object)val != (Object)null)
				{
					return val.displayName;
				}
			}
			catch
			{
			}
			try
			{
				BasePlayer val2 = BasePlayer.FindSleeping(P_0);
				if ((Object)(object)val2 != (Object)null)
				{
					return val2.displayName;
				}
			}
			catch
			{
			}
			try
			{
				Enumerator<BasePlayer> enumerator = BasePlayer.activePlayerList.GetEnumerator();
				try
				{
					while (enumerator.MoveNext())
					{
						BasePlayer current = enumerator.Current;
						if ((Object)(object)current != (Object)null && EncryptedValue<ulong>.op_Implicit(current.userID) == P_0)
						{
							return current.displayName;
						}
					}
				}
				finally
				{
					((IDisposable)enumerator).Dispose();
				}
			}
			catch
			{
			}
			return null;
		}

		private string A(ulong P_0, ulong P_1, DateTime P_2)
		{
			try
			{
				string arg = P_2.ToUniversalTime().ToString("yyyyMMddTHHmmssfffZ");
				return $"report-{arg}-{P_0}-{P_1}";
			}
			catch
			{
				return $"report-{DateTime.UtcNow.Ticks}-{P_1}-{P_0}";
			}
		}

		private static DateTimeOffset D()
		{
			try
			{
				return DateTimeOffset.Now;
			}
			catch
			{
				return DateTimeOffset.UtcNow;
			}
		}

		private static double A(DateTimeOffset P_0)
		{
			try
			{
				return (double)P_0.ToUnixTimeMilliseconds() / 1000.0;
			}
			catch
			{
				return (double)P_0.ToUniversalTime().ToUnixTimeMilliseconds() / 1000.0;
			}
		}

		private static string a(DateTimeOffset P_0)
		{
			try
			{
				return P_0.ToString("o");
			}
			catch
			{
				return P_0.UtcDateTime.ToString("o");
			}
		}

		private static string A(DateTime P_0)
		{
			try
			{
				return new DateTimeOffset(P_0, TimeSpan.Zero).ToLocalTime().ToString("o");
			}
			catch
			{
				try
				{
					return P_0.ToLocalTime().ToString("o");
				}
				catch
				{
					return P_0.ToString("o");
				}
			}
		}

		private static DateTime A(string P_0, DateTime P_1)
		{
			if (string.IsNullOrWhiteSpace(P_0))
			{
				return P_1;
			}
			try
			{
				if (DateTime.TryParse(P_0, out var result))
				{
					return DateTime.SpecifyKind(result, DateTimeKind.Utc);
				}
			}
			catch
			{
			}
			return P_1;
		}

		private static string b(string P_0)
		{
			if (string.IsNullOrWhiteSpace(P_0))
			{
				return null;
			}
			string text = P_0.Trim();
			if (text.Length != 0)
			{
				return text;
			}
			return null;
		}

		private static string A(object P_0)
		{
			try
			{
				if (P_0 == null)
				{
					return null;
				}
				MethodInfo methodInfo = A(P_0.GetType(), "String", m.m_A);
				if (methodInfo == null)
				{
					return null;
				}
				object[] parameters = ((!methodInfo.IsStatic) ? null : new object[1] { P_0 });
				return methodInfo.Invoke(methodInfo.IsStatic ? null : P_0, parameters) as string;
			}
			catch
			{
				return null;
			}
		}

		private static string a(object P_0)
		{
			try
			{
				if (P_0 == null)
				{
					return null;
				}
				MethodInfo methodInfo = A(P_0.GetType(), "StringMultiLine", m.m_a);
				if (methodInfo == null)
				{
					return null;
				}
				object[] parameters = ((!methodInfo.IsStatic) ? null : new object[1] { P_0 });
				return methodInfo.Invoke(methodInfo.IsStatic ? null : P_0, parameters) as string;
			}
			catch
			{
				return null;
			}
		}

		private static MethodInfo A(Type P_0, string P_1)
		{
			if (P_0 == null)
			{
				return null;
			}
			MethodInfo method = P_0.GetMethod(P_1, BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic, null, Type.EmptyTypes, null);
			if (method != null && method.ReturnType == typeof(string))
			{
				return method;
			}
			Assembly[] assemblies = AppDomain.CurrentDomain.GetAssemblies();
			foreach (Assembly assembly in assemblies)
			{
				Type[] types;
				try
				{
					types = assembly.GetTypes();
				}
				catch
				{
					continue;
				}
				Type[] array = types;
				foreach (Type type in array)
				{
					MethodInfo[] methods;
					try
					{
						methods = type.GetMethods(BindingFlags.Static | BindingFlags.Public | BindingFlags.NonPublic);
					}
					catch
					{
						continue;
					}
					foreach (MethodInfo methodInfo in methods)
					{
						if (string.Equals(methodInfo.Name, P_1, StringComparison.Ordinal) && !(methodInfo.ReturnType != typeof(string)))
						{
							ParameterInfo[] parameters = methodInfo.GetParameters();
							if (parameters.Length == 1 && parameters[0].ParameterType.IsAssignableFrom(P_0))
							{
								return methodInfo;
							}
						}
					}
				}
			}
			return null;
		}

		private static MethodInfo A(Type P_0, string P_1, Dictionary<Type, MethodInfo> P_2)
		{
			if (P_0 == null || P_2 == null)
			{
				return null;
			}
			lock (m.m_A)
			{
				if (P_2.TryGetValue(P_0, out var value))
				{
					return value;
				}
				return P_2[P_0] = A(P_0, P_1);
			}
		}

		private static bool A(object P_0, out int P_1)
		{
			P_1 = 0;
			try
			{
				PropertyInfo property = P_0.GetType().GetProperty("Position", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
				if (property != null && property.CanRead)
				{
					object value = property.GetValue(P_0, null);
					if (value is int num)
					{
						P_1 = num;
						return true;
					}
					if (value is long num2)
					{
						P_1 = (int)num2;
						return true;
					}
				}
				FieldInfo fieldInfo = P_0.GetType().GetField("Position", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic) ?? P_0.GetType().GetField("position", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic) ?? P_0.GetType().GetField("pos", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
				if (fieldInfo != null)
				{
					object value2 = fieldInfo.GetValue(P_0);
					if (value2 is int num3)
					{
						P_1 = num3;
						return true;
					}
					if (value2 is long num4)
					{
						P_1 = (int)num4;
						return true;
					}
				}
			}
			catch
			{
			}
			return false;
		}

		private static void A(object P_0, int P_1)
		{
			try
			{
				PropertyInfo property = P_0.GetType().GetProperty("Position", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
				if (property != null && property.CanWrite)
				{
					property.SetValue(P_0, P_1, null);
					return;
				}
				FieldInfo fieldInfo = P_0.GetType().GetField("Position", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic) ?? P_0.GetType().GetField("position", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic) ?? P_0.GetType().GetField("pos", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
				if (fieldInfo != null)
				{
					fieldInfo.SetValue(P_0, P_1);
				}
			}
			catch
			{
			}
		}

		private static string A(Func<string> P_0)
		{
			try
			{
				return P_0?.Invoke() ?? "";
			}
			catch
			{
				return "";
			}
		}
	}
	internal interface B
	{
		bool Initialized { get; }

		bool Ready { get; }

		void A();
	}
	internal static class b
	{
		public static Dictionary<string, bool> A(Dictionary<string, bool> P_0)
		{
			if (P_0 == null || P_0.Count == 0)
			{
				return null;
			}
			Dictionary<string, bool> dictionary = new Dictionary<string, bool>(StringComparer.OrdinalIgnoreCase);
			foreach (KeyValuePair<string, bool> item in P_0)
			{
				string text = A(item.Key);
				if (!string.IsNullOrWhiteSpace(text))
				{
					dictionary[text] = item.Value;
				}
			}
			if (dictionary.Count <= 0)
			{
				return null;
			}
			return dictionary;
		}

		public static bool A(Dictionary<string, bool> P_0, string P_1, string P_2)
		{
			if (P_0 == null || P_0.Count == 0)
			{
				return false;
			}
			string text = A(P_1);
			if (!string.IsNullOrEmpty(text) && P_0.TryGetValue(text, out var value))
			{
				return !value;
			}
			string text2 = A(P_2);
			if (!string.IsNullOrEmpty(text2) && P_0.TryGetValue(text2, out var value2))
			{
				return !value2;
			}
			return false;
		}

		public static string A(string P_0)
		{
			if (string.IsNullOrWhiteSpace(P_0))
			{
				return null;
			}
			StringBuilder stringBuilder = new StringBuilder(P_0.Length);
			bool flag = false;
			string text = P_0.Trim();
			for (int l = 0; l < text.Length; l++)
			{
				char c2 = char.ToLowerInvariant(text[l]);
				if (char.IsLetterOrDigit(c2))
				{
					stringBuilder.Append(c2);
					flag = false;
				}
				else if ((c2 == '_' || c2 == '-' || char.IsWhiteSpace(c2)) && !flag && stringBuilder.Length > 0)
				{
					stringBuilder.Append('_');
					flag = true;
				}
			}
			string text2 = stringBuilder.ToString().Trim('_');
			if (!string.IsNullOrWhiteSpace(text2))
			{
				return text2;
			}
			return null;
		}
	}
	internal sealed class N : B
	{
		internal sealed class A
		{
			public ulong A;

			public string A;

			public string a;

			public string B;

			public DateTime A;

			public double A;
		}

		internal sealed class a
		{
			public ulong A;

			public string A;

			public string a;

			public string B;

			public DateTime A;

			public DateTime a;

			public string b;
		}

		private sealed class B
		{
			public ulong A;

			public string A;

			public string a;

			public string B;

			public DateTime A;

			public double A;
		}

		[Serializable]
		[CompilerGenerated]
		private sealed class b
		{
			public static readonly b A = new b();

			public static Comparison<J> A;

			internal int A(J P_0, J P_1)
			{
				return P_0.chunkOffsetSeconds.CompareTo(P_1.chunkOffsetSeconds);
			}
		}

		private const int m_A = 15;

		private const int m_a = 2000;

		private const bool m_A = true;

		private const bool m_a = true;

		private const bool m_B = true;

		private const string m_A = ".meta.json";

		private const string m_a = ".events.json";

		private static readonly UTF8Encoding m_A = new UTF8Encoding(encoderShouldEmitUTF8Identifier: false);

		private static readonly object m_A = new object();

		private static readonly JsonSerializer m_A = JsonSerializer.Create(new JsonSerializerSettings
		{
			NullValueHandling = (NullValueHandling)1,
			DefaultValueHandling = (DefaultValueHandling)1
		});

		private readonly M m_A;

		private readonly global::A.a m_A;

		private readonly Dictionary<ulong, B> m_A = new Dictionary<ulong, B>();

		private readonly Dictionary<ulong, DateTime> m_A = new Dictionary<ulong, DateTime>();

		private readonly Dictionary<ulong, DateTime> m_a = new Dictionary<ulong, DateTime>();

		private readonly object m_a = new object();

		private bool m_b;

		private bool m_C;

		private TimeSpan m_A = TimeSpan.Zero;

		[CompilerGenerated]
		private Action<a> m_A;

		[CompilerGenerated]
		private bool m_c;

		[CompilerGenerated]
		private bool m_D;

		public bool Initialized
		{
			[CompilerGenerated]
			get
			{
				return this.m_c;
			}
			[CompilerGenerated]
			private set
			{
				this.m_c = value;
			}
		}

		public bool Ready
		{
			[CompilerGenerated]
			get
			{
				return this.m_D;
			}
			[CompilerGenerated]
			private set
			{
				this.m_D = value;
			}
		}

		public event Action<a> ChunkFinalized
		{
			[CompilerGenerated]
			add
			{
				Action<a> action = this.m_A;
				Action<a> action2;
				do
				{
					action2 = action;
					Action<a> value2 = (Action<a>)Delegate.Combine(action2, value);
					action = Interlocked.CompareExchange(ref this.m_A, value2, action2);
				}
				while ((object)action != action2);
			}
			[CompilerGenerated]
			remove
			{
				Action<a> action = this.m_A;
				Action<a> action2;
				do
				{
					action2 = action;
					Action<a> value2 = (Action<a>)Delegate.Remove(action2, value);
					action = Interlocked.CompareExchange(ref this.m_A, value2, action2);
				}
				while ((object)action != action2);
			}
		}

		public N(M P_0, global::A.a P_1)
		{
			this.m_A = P_0;
			this.m_A = P_1;
		}

		public void A()
		{
			Initialized = true;
			Ready = true;
			this.m_A?.a(null);
			e();
		}

		public void A(BasePlayer P_0)
		{
			b(P_0);
		}

		public void a(BasePlayer P_0)
		{
			b(P_0);
		}

		public void B(BasePlayer P_0)
		{
			A(P_0, "disconnect");
		}

		public void A(DateTime P_0)
		{
			if (!B())
			{
				return;
			}
			a(P_0);
			List<ulong> list = null;
			lock (this.m_a)
			{
				foreach (KeyValuePair<ulong, DateTime> item in this.m_A)
				{
					if (item.Value <= P_0)
					{
						if (list == null)
						{
							list = new List<ulong>();
						}
						list.Add(item.Key);
					}
				}
			}
			if (list != null && list.Count != 0)
			{
				for (int l = 0; l < list.Count; l++)
				{
					a(list[l]);
				}
			}
		}

		public bool A(ulong P_0, out A P_1)
		{
			P_1 = null;
			if (P_0 == 0L)
			{
				return false;
			}
			B value;
			lock (this.m_a)
			{
				this.m_A.TryGetValue(P_0, out value);
			}
			if (value == null || string.IsNullOrEmpty(value.B))
			{
				return false;
			}
			P_1 = new A
			{
				A = value.A,
				A = value.A,
				a = value.a,
				B = value.B,
				A = value.A,
				A = value.A
			};
			return true;
		}

		public HashSet<string> a()
		{
			HashSet<string> hashSet = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
			lock (this.m_a)
			{
				foreach (KeyValuePair<ulong, B> item in this.m_A)
				{
					try
					{
						string text = ((item.Value != null) ? item.Value.B : null);
						if (!string.IsNullOrEmpty(text))
						{
							try
							{
								text = Path.GetFullPath(text);
							}
							catch
							{
							}
							hashSet.Add(text);
						}
					}
					catch
					{
					}
				}
				return hashSet;
			}
		}

		public bool A(string P_0, HashSet<string> P_1)
		{
			if (string.IsNullOrEmpty(P_0) || P_1 == null || P_1.Count == 0)
			{
				return false;
			}
			try
			{
				string fullPath = Path.GetFullPath(P_0);
				if (P_1.Contains(fullPath))
				{
					return true;
				}
			}
			catch
			{
			}
			return P_1.Contains(P_0);
		}

		public bool A(ulong P_0, string P_1)
		{
			//IL_0106: Unknown result type (might be due to invalid IL or missing references)
			if (!B())
			{
				return false;
			}
			if (P_0 == 0L)
			{
				return false;
			}
			B value;
			lock (this.m_a)
			{
				this.m_A.TryGetValue(P_0, out value);
			}
			if (value == null)
			{
				return false;
			}
			BasePlayer val = BasePlayer.FindByID(P_0);
			if ((Object)(object)val == (Object)null || !val.IsConnected || val.Connection == null)
			{
				return false;
			}
			try
			{
				if (val.Connection.IsRecording)
				{
					c(val);
				}
			}
			catch
			{
			}
			try
			{
				string value2 = D(val);
				if (string.IsNullOrEmpty(value2))
				{
					value2 = A(value);
				}
				if (!string.IsNullOrEmpty(value2))
				{
					value.B = value2;
				}
			}
			catch
			{
			}
			try
			{
				A(value, P_1);
			}
			catch
			{
			}
			if (!val.IsConnected)
			{
				return false;
			}
			string value3 = C(val);
			if (string.IsNullOrEmpty(value3))
			{
				Debug.LogWarning((object)$"[RustDemoPro] Report flush: failed to restart recording for {P_0} ({val.displayName}).");
				B(P_0, DateTime.UtcNow);
				return false;
			}
			value.A = EncryptedValue<ulong>.op_Implicit(val.userID);
			value.A = val.displayName;
			value.a = val.UserIDString;
			value.B = value3;
			DateTimeOffset dateTimeOffset = D();
			value.A = dateTimeOffset.UtcDateTime;
			value.A = A(dateTimeOffset);
			this.m_A?.B(P_0);
			B(P_0, value.A);
			return true;
		}

		public void A(BaseCombatEntity P_0, HitInfo P_1)
		{
			//IL_01cf: Unknown result type (might be due to invalid IL or missing references)
			//IL_01da: Unknown result type (might be due to invalid IL or missing references)
			//IL_004b: Unknown result type (might be due to invalid IL or missing references)
			//IL_0096: Unknown result type (might be due to invalid IL or missing references)
			//IL_0243: Unknown result type (might be due to invalid IL or missing references)
			//IL_02c9: Unknown result type (might be due to invalid IL or missing references)
			if (!B() || (Object)(object)P_0 == (Object)null || P_1 == null)
			{
				return;
			}
			BasePlayer val = ((BaseEntity)P_0).ToPlayer();
			BasePlayer initiatorPlayer = P_1.InitiatorPlayer;
			B value = null;
			B value2 = null;
			if ((Object)(object)val != (Object)null)
			{
				lock (this.m_a)
				{
					this.m_A.TryGetValue(EncryptedValue<ulong>.op_Implicit(val.userID), out value);
				}
				_ = ((BaseEntity)val).IsNpc;
			}
			if ((Object)(object)initiatorPlayer != (Object)null)
			{
				lock (this.m_a)
				{
					this.m_A.TryGetValue(EncryptedValue<ulong>.op_Implicit(initiatorPlayer.userID), out value2);
				}
				_ = ((BaseEntity)initiatorPlayer).IsNpc;
			}
			if (value == null && value2 == null)
			{
				return;
			}
			DateTimeOffset dateTimeOffset = D();
			double serverSeconds = A(dateTimeOffset);
			float num = 0f;
			float num2 = 0f;
			float num3 = 0f;
			try
			{
				num = P_0.health;
			}
			catch
			{
			}
			try
			{
				num2 = ((P_1.damageTypes != null) ? P_1.damageTypes.Total() : 0f);
			}
			catch
			{
			}
			num3 = Mathf.Max(0f, num - num2);
			string weaponShortName = null;
			string weaponPrefab = null;
			try
			{
				if ((Object)(object)P_1.Weapon != (Object)null)
				{
					weaponShortName = ((BaseNetworkable)P_1.Weapon).ShortPrefabName;
					weaponPrefab = ((BaseNetworkable)P_1.Weapon).PrefabName;
				}
				else if ((Object)(object)P_1.WeaponPrefab != (Object)null)
				{
					weaponShortName = ((BaseNetworkable)P_1.WeaponPrefab).ShortPrefabName;
					weaponPrefab = ((BaseNetworkable)P_1.WeaponPrefab).PrefabName;
				}
			}
			catch
			{
			}
			string ammoPrefab = null;
			try
			{
				if ((Object)(object)P_1.ProjectilePrefab != (Object)null)
				{
					ammoPrefab = ((Object)P_1.ProjectilePrefab).name;
				}
			}
			catch
			{
			}
			string hitArea = null;
			try
			{
				hitArea = P_1.boneName;
			}
			catch
			{
			}
			float distance = 0f;
			try
			{
				if ((Object)(object)initiatorPlayer != (Object)null)
				{
					distance = Vector3.Distance(((Component)initiatorPlayer).transform.position, ((Component)P_0).transform.position);
				}
			}
			catch
			{
			}
			if (!((Object)(object)initiatorPlayer == (Object)null) && !((BaseEntity)initiatorPlayer).IsNpc && !((Object)(object)val == (Object)null) && !((BaseEntity)val).IsNpc)
			{
				J j2 = new J
				{
					serverSeconds = serverSeconds,
					serverTimeLocal = a(dateTimeOffset),
					type = "Hit",
					attackerUserId = (((Object)(object)initiatorPlayer != (Object)null) ? EncryptedValue<ulong>.op_Implicit(initiatorPlayer.userID) : 0),
					attackerEntityId = (((Object)(object)initiatorPlayer != (Object)null) ? A((BaseNetworkable)(object)initiatorPlayer) : 0),
					attackerName = (((Object)(object)initiatorPlayer != (Object)null) ? initiatorPlayer.displayName : (((Object)(object)P_1.Initiator != (Object)null) ? ((BaseNetworkable)P_1.Initiator).ShortPrefabName : "unknown")),
					attackerIsNpc = ((Object)(object)initiatorPlayer != (Object)null && ((BaseEntity)initiatorPlayer).IsNpc),
					targetUserId = (((Object)(object)val != (Object)null) ? EncryptedValue<ulong>.op_Implicit(val.userID) : 0),
					targetEntityId = A((BaseNetworkable)(object)P_0),
					targetName = (((Object)(object)val != (Object)null) ? val.displayName : ((BaseNetworkable)P_0).ShortPrefabName),
					targetIsNpc = ((Object)(object)val != (Object)null && ((BaseEntity)val).IsNpc),
					weaponPrefab = weaponPrefab,
					weaponShortName = weaponShortName,
					ammoPrefab = ammoPrefab,
					hitArea = hitArea,
					distance = distance,
					oldHp = num,
					predictedNewHp = num3,
					damageTotal = num2,
					info = null
				};
				if (value2 != null)
				{
					A(value2, j2);
				}
				if (value != null && value != value2)
				{
					A(value, j2);
				}
			}
		}

		public void a(BaseCombatEntity P_0, HitInfo P_1)
		{
			//IL_003e: Unknown result type (might be due to invalid IL or missing references)
			//IL_00b0: Unknown result type (might be due to invalid IL or missing references)
			//IL_012c: Unknown result type (might be due to invalid IL or missing references)
			//IL_01fe: Unknown result type (might be due to invalid IL or missing references)
			//IL_024b: Unknown result type (might be due to invalid IL or missing references)
			//IL_027b: Unknown result type (might be due to invalid IL or missing references)
			if (!B() || (Object)(object)P_0 == (Object)null)
			{
				return;
			}
			BasePlayer val = ((BaseEntity)P_0).ToPlayer();
			if ((Object)(object)val == (Object)null)
			{
				return;
			}
			B value;
			lock (this.m_a)
			{
				this.m_A.TryGetValue(EncryptedValue<ulong>.op_Implicit(val.userID), out value);
			}
			if (value == null)
			{
				return;
			}
			BasePlayer val2 = ((P_1 != null) ? P_1.InitiatorPlayer : null);
			DateTimeOffset dateTimeOffset = D();
			double serverSeconds = A(dateTimeOffset);
			J j2 = new J
			{
				serverSeconds = serverSeconds,
				serverTimeLocal = a(dateTimeOffset),
				type = "Death",
				attackerUserId = (((Object)(object)val2 != (Object)null) ? EncryptedValue<ulong>.op_Implicit(val2.userID) : 0),
				attackerEntityId = (((Object)(object)val2 != (Object)null) ? A((BaseNetworkable)(object)val2) : 0),
				attackerName = (((Object)(object)val2 != (Object)null) ? val2.displayName : ((P_1 != null && (Object)(object)P_1.Initiator != (Object)null) ? ((BaseNetworkable)P_1.Initiator).ShortPrefabName : "unknown")),
				attackerIsNpc = ((Object)(object)val2 != (Object)null && ((BaseEntity)val2).IsNpc),
				targetUserId = EncryptedValue<ulong>.op_Implicit(val.userID),
				targetEntityId = A((BaseNetworkable)(object)val),
				targetName = val.displayName,
				targetIsNpc = ((BaseEntity)val).IsNpc,
				weaponPrefab = null,
				weaponShortName = null,
				ammoPrefab = null,
				hitArea = null,
				distance = 0f,
				oldHp = ((BaseCombatEntity)val).health,
				predictedNewHp = 0f,
				damageTotal = 0f,
				info = null
			};
			if ((Object)(object)val2 == (Object)null || ((BaseEntity)val2).IsNpc)
			{
				return;
			}
			A(value, j2);
			if ((Object)(object)val2 != (Object)null && !((BaseEntity)val2).IsNpc)
			{
				B value2;
				lock (this.m_a)
				{
					this.m_A.TryGetValue(EncryptedValue<ulong>.op_Implicit(val2.userID), out value2);
				}
				if (value2 != null)
				{
					J j3 = new J
					{
						serverSeconds = serverSeconds,
						serverTimeLocal = a(dateTimeOffset),
						type = "Kill",
						attackerUserId = EncryptedValue<ulong>.op_Implicit(val2.userID),
						attackerEntityId = A((BaseNetworkable)(object)val2),
						attackerName = val2.displayName,
						attackerIsNpc = false,
						targetUserId = EncryptedValue<ulong>.op_Implicit(val.userID),
						targetEntityId = A((BaseNetworkable)(object)val),
						targetName = val.displayName,
						targetIsNpc = ((BaseEntity)val).IsNpc,
						weaponPrefab = null,
						weaponShortName = null,
						ammoPrefab = null,
						hitArea = null,
						distance = 0f,
						oldHp = 0f,
						predictedNewHp = 0f,
						damageTotal = 0f,
						info = null
					};
					A(value2, j3);
				}
			}
		}

		public void A(ulong P_0)
		{
			BasePlayer val = BasePlayer.FindByID(P_0);
			b(val);
		}

		public void b(BasePlayer P_0)
		{
			//IL_0036: Unknown result type (might be due to invalid IL or missing references)
			//IL_0066: Unknown result type (might be due to invalid IL or missing references)
			//IL_0086: Unknown result type (might be due to invalid IL or missing references)
			//IL_00a5: Unknown result type (might be due to invalid IL or missing references)
			//IL_00f7: Unknown result type (might be due to invalid IL or missing references)
			//IL_0145: Unknown result type (might be due to invalid IL or missing references)
			//IL_0156: Unknown result type (might be due to invalid IL or missing references)
			//IL_0178: Unknown result type (might be due to invalid IL or missing references)
			if (!B() || (Object)(object)P_0 == (Object)null || !P_0.IsConnected || P_0.Connection == null || ((BaseEntity)P_0).IsNpc || (this.m_C && !a(EncryptedValue<ulong>.op_Implicit(P_0.userID), DateTime.UtcNow)))
			{
				return;
			}
			B value;
			lock (this.m_a)
			{
				if (!this.m_A.TryGetValue(EncryptedValue<ulong>.op_Implicit(P_0.userID), out value))
				{
					value = new B();
					this.m_A[EncryptedValue<ulong>.op_Implicit(P_0.userID)] = value;
				}
			}
			value.A = EncryptedValue<ulong>.op_Implicit(P_0.userID);
			value.A = P_0.displayName;
			value.a = P_0.UserIDString;
			if (P_0.Connection.IsRecording)
			{
				c(P_0);
			}
			string text = C(P_0);
			if (string.IsNullOrEmpty(text))
			{
				Debug.LogWarning((object)$"[RustDemoPro] Failed to start recording for {P_0.userID} ({P_0.displayName}).");
				return;
			}
			value.B = text;
			DateTimeOffset dateTimeOffset = D();
			value.A = dateTimeOffset.UtcDateTime;
			value.A = A(dateTimeOffset);
			this.m_A?.B(EncryptedValue<ulong>.op_Implicit(P_0.userID));
			B(EncryptedValue<ulong>.op_Implicit(P_0.userID), value.A);
			if (b())
			{
				Debug.Log((object)$"[RustDemoPro] Started demo recording for {P_0.userID} ({P_0.displayName}) -> {text}");
			}
		}

		public void A(BasePlayer P_0, string P_1)
		{
			//IL_000c: Unknown result type (might be due to invalid IL or missing references)
			if (!((Object)(object)P_0 == (Object)null))
			{
				a(EncryptedValue<ulong>.op_Implicit(P_0.userID), P_1);
			}
		}

		public void a(ulong P_0, string P_1)
		{
			B value;
			lock (this.m_a)
			{
				this.m_A.TryGetValue(P_0, out value);
			}
			lock (this.m_a)
			{
				this.m_A.Remove(P_0);
			}
			BasePlayer val = BasePlayer.FindByID(P_0);
			if ((Object)(object)val != (Object)null && val.Connection != null)
			{
				try
				{
					if (val.Connection.IsRecording)
					{
						c(val);
						if (C())
						{
							Debug.Log((object)$"[RustDemoPro] Stopped demo recording for {P_0} ({val.displayName}) [{P_1}]");
						}
					}
					string value2 = D(val);
					if (string.IsNullOrEmpty(value2))
					{
						value2 = A(value);
					}
					if (!string.IsNullOrEmpty(value2) && value != null)
					{
						value.B = value2;
					}
				}
				catch
				{
				}
			}
			try
			{
				A(value, P_1);
			}
			catch
			{
			}
			lock (this.m_a)
			{
				if (this.m_A.ContainsKey(P_0))
				{
					this.m_A.Remove(P_0);
				}
			}
			this.m_A?.b(P_0);
		}

		public void a(ulong P_0)
		{
			//IL_0165: Unknown result type (might be due to invalid IL or missing references)
			if (!B())
			{
				return;
			}
			BasePlayer val = BasePlayer.FindByID(P_0);
			if ((Object)(object)val == (Object)null || !val.IsConnected || val.Connection == null)
			{
				a(P_0, "rotate-disconnected");
				return;
			}
			if (this.m_C && !a(P_0, DateTime.UtcNow))
			{
				a(P_0, "performance-expired");
				return;
			}
			B value;
			lock (this.m_a)
			{
				this.m_A.TryGetValue(P_0, out value);
			}
			try
			{
				if (val.Connection != null && val.Connection.IsRecording)
				{
					c(val);
				}
			}
			catch
			{
			}
			try
			{
				string value2 = D(val);
				if (string.IsNullOrEmpty(value2))
				{
					value2 = A(value);
				}
				if (!string.IsNullOrEmpty(value2) && value != null)
				{
					value.B = value2;
				}
			}
			catch
			{
			}
			try
			{
				A(value, "rotate");
			}
			catch
			{
			}
			string text = C(val);
			if (string.IsNullOrEmpty(text))
			{
				Debug.LogWarning((object)$"[RustDemoPro] Rotate: failed to restart recording for {P_0} ({val.displayName}).");
				B(P_0, DateTime.UtcNow);
				return;
			}
			if (value == null)
			{
				value = new B();
				lock (this.m_a)
				{
					this.m_A[P_0] = value;
				}
			}
			value.A = EncryptedValue<ulong>.op_Implicit(val.userID);
			value.A = val.displayName;
			value.a = val.UserIDString;
			value.B = text;
			DateTimeOffset dateTimeOffset = D();
			value.A = dateTimeOffset.UtcDateTime;
			value.A = A(dateTimeOffset);
			this.m_A?.B(P_0);
			B(P_0, value.A);
			if (c())
			{
				Debug.Log((object)$"[RustDemoPro] Rotated demo recording for {P_0} ({val.displayName}) -> {text}");
			}
		}

		public void A(bool P_0, double P_1)
		{
			this.m_C = P_0;
			this.m_A = TimeSpan.FromHours(Math.Max(0.0, P_1));
			if (!this.m_C)
			{
				lock (this.m_a)
				{
					this.m_a.Clear();
					return;
				}
			}
			DateTime utcNow = DateTime.UtcNow;
			List<ulong> list = null;
			lock (this.m_a)
			{
				foreach (KeyValuePair<ulong, B> item in this.m_A)
				{
					if (!a(item.Key, utcNow))
					{
						if (list == null)
						{
							list = new List<ulong>();
						}
						list.Add(item.Key);
					}
				}
			}
			if (list == null)
			{
				return;
			}
			foreach (ulong item2 in list)
			{
				a(item2, "performance-disabled");
			}
		}

		public void A(ulong P_0, DateTime P_1)
		{
			if (!this.m_C || P_0 == 0L)
			{
				return;
			}
			DateTime dateTime = P_1 + this.m_A;
			if (this.m_A <= TimeSpan.Zero)
			{
				return;
			}
			lock (this.m_a)
			{
				if (this.m_a.TryGetValue(P_0, out var value) && value > dateTime)
				{
					return;
				}
				this.m_a[P_0] = dateTime;
			}
			BasePlayer val = BasePlayer.FindByID(P_0);
			if ((Object)(object)val != (Object)null && val.IsConnected)
			{
				b(val);
			}
		}

		public bool a(ulong P_0, DateTime P_1)
		{
			if (!this.m_C)
			{
				return true;
			}
			lock (this.m_a)
			{
				if (this.m_a.TryGetValue(P_0, out var value))
				{
					return value >= P_1;
				}
			}
			return false;
		}

		private void a(DateTime P_0)
		{
			if (!this.m_C)
			{
				return;
			}
			lock (this.m_a)
			{
				foreach (KeyValuePair<ulong, DateTime> item in this.m_a.ToList())
				{
					if (item.Value < P_0)
					{
						this.m_a.Remove(item.Key);
					}
				}
			}
		}

		private void B(ulong P_0, DateTime P_1)
		{
			DateTime value = P_1.AddMinutes(15.0);
			lock (this.m_a)
			{
				this.m_A[P_0] = value;
			}
		}

		private static bool B()
		{
			return (SingletonComponent<global::A.C>.Instance?.Config?.Enabled).GetValueOrDefault();
		}

		private static bool b()
		{
			return (SingletonComponent<global::A.C>.Instance?.Config?.Logging?.Starts).GetValueOrDefault();
		}

		private static bool C()
		{
			return (SingletonComponent<global::A.C>.Instance?.Config?.Logging?.Stops).GetValueOrDefault();
		}

		private static bool c()
		{
			return (SingletonComponent<global::A.C>.Instance?.Config?.Logging?.Rotations).GetValueOrDefault();
		}

		private static DateTimeOffset D()
		{
			try
			{
				return DateTimeOffset.Now;
			}
			catch
			{
				return DateTimeOffset.UtcNow;
			}
		}

		private static double A(DateTimeOffset P_0)
		{
			try
			{
				return (double)P_0.ToUnixTimeMilliseconds() / 1000.0;
			}
			catch
			{
				return (double)P_0.ToUniversalTime().ToUnixTimeMilliseconds() / 1000.0;
			}
		}

		private static string a(DateTimeOffset P_0)
		{
			try
			{
				return P_0.ToString("o");
			}
			catch
			{
				return P_0.UtcDateTime.ToString("o");
			}
		}

		private static string B(DateTime P_0)
		{
			try
			{
				return new DateTimeOffset(P_0, TimeSpan.Zero).ToLocalTime().ToString("o");
			}
			catch
			{
				try
				{
					return P_0.ToLocalTime().ToString("o");
				}
				catch
				{
					return P_0.ToString("o");
				}
			}
		}

		private void A(B P_0, J P_1)
		{
			if (P_0 == null || string.IsNullOrEmpty(P_0.B) || this.m_A == null)
			{
				return;
			}
			double num = 0.0;
			try
			{
				num = (DateTime.UtcNow - P_0.A).TotalSeconds;
				if (num < 0.0)
				{
					num = 0.0;
				}
			}
			catch
			{
			}
			J j2 = new J
			{
				serverSeconds = P_1.serverSeconds,
				serverTimeLocal = P_1.serverTimeLocal,
				chunkOffsetSeconds = num,
				type = P_1.type,
				attackerUserId = P_1.attackerUserId,
				attackerEntityId = P_1.attackerEntityId,
				attackerName = P_1.attackerName,
				attackerIsNpc = P_1.attackerIsNpc,
				targetUserId = P_1.targetUserId,
				targetEntityId = P_1.targetEntityId,
				targetName = P_1.targetName,
				targetIsNpc = P_1.targetIsNpc,
				weaponPrefab = P_1.weaponPrefab,
				weaponShortName = P_1.weaponShortName,
				ammoPrefab = P_1.ammoPrefab,
				hitArea = P_1.hitArea,
				distance = P_1.distance,
				oldHp = P_1.oldHp,
				predictedNewHp = P_1.predictedNewHp,
				damageTotal = P_1.damageTotal,
				info = P_1.info
			};
			this.m_A.A(P_0.A, j2, 2000);
		}

		private static ulong A(BaseNetworkable P_0)
		{
			try
			{
				if ((Object)(object)P_0 == (Object)null)
				{
					return 0uL;
				}
				Networkable net = P_0.net;
				if (net == null)
				{
					return 0uL;
				}
				object obj = ((object)net).GetType().GetProperty("ID")?.GetValue(net, null);
				if (obj == null)
				{
					return 0uL;
				}
				PropertyInfo property = obj.GetType().GetProperty("Value");
				if (property?.GetValue(obj, null) is ulong result)
				{
					return result;
				}
				if (property?.GetValue(obj, null) is long result2)
				{
					return (ulong)result2;
				}
				if (property?.GetValue(obj, null) is int num)
				{
					return (ulong)num;
				}
				if (obj is ulong result3)
				{
					return result3;
				}
			}
			catch
			{
				return 0uL;
			}
			return 0uL;
		}

		private static int d()
		{
			try
			{
				if ((Type.GetType("Rust.Protocol, Assembly-CSharp")?.GetField("network", BindingFlags.Static | BindingFlags.Public))?.GetValue(null) is int result)
				{
					return result;
				}
			}
			catch
			{
				return 0;
			}
			return 0;
		}

		private string C(BasePlayer P_0)
		{
			try
			{
				try
				{
					string text = E();
					if (!string.IsNullOrEmpty(text))
					{
						Directory.CreateDirectory(text);
					}
				}
				catch
				{
				}
				try
				{
					ILogger unityLogger = Debug.unityLogger;
					bool logEnabled = unityLogger == null || unityLogger.logEnabled;
					try
					{
						if (unityLogger != null)
						{
							unityLogger.logEnabled = false;
						}
						P_0.StartDemoRecording();
						string text2 = D(P_0);
						if (!string.IsNullOrEmpty(text2))
						{
							return text2;
						}
						return a(E());
					}
					finally
					{
						if (unityLogger != null)
						{
							unityLogger.logEnabled = logEnabled;
						}
					}
				}
				catch
				{
				}
				return null;
			}
			catch
			{
				return null;
			}
		}

		private bool c(BasePlayer P_0)
		{
			try
			{
				if ((Object)(object)P_0 == (Object)null)
				{
					return false;
				}
				try
				{
					ILogger unityLogger = Debug.unityLogger;
					bool logEnabled = unityLogger == null || unityLogger.logEnabled;
					try
					{
						if (unityLogger != null)
						{
							unityLogger.logEnabled = false;
						}
						P_0.StopDemoRecording();
						return true;
					}
					finally
					{
						if (unityLogger != null)
						{
							unityLogger.logEnabled = logEnabled;
						}
					}
				}
				catch
				{
				}
				Connection connection = P_0.Connection;
				if (connection == null)
				{
					return false;
				}
				MethodInfo method = ((object)connection).GetType().GetMethod("StopRecording", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
				if (method != null)
				{
					method.Invoke(connection, null);
					return true;
				}
				return false;
			}
			catch
			{
				return false;
			}
		}

		private string D(BasePlayer P_0)
		{
			try
			{
				Connection val = (((Object)(object)P_0 != (Object)null) ? P_0.Connection : null);
				if (val == null)
				{
					return null;
				}
				PropertyInfo property = ((object)val).GetType().GetProperty("RecordFilename", BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
				string text = ((property != null) ? (property.GetValue(val, null) as string) : null);
				return A(text);
			}
			catch
			{
				return null;
			}
		}

		private string A(string P_0)
		{
			try
			{
				if (string.IsNullOrEmpty(P_0))
				{
					return null;
				}
				if (Path.IsPathRooted(P_0))
				{
					return P_0;
				}
				string text = P_0.TrimStart(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
				if (text.StartsWith("demos", StringComparison.OrdinalIgnoreCase))
				{
					text = text.Substring("demos".Length).TrimStart(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
				}
				return Path.Combine(E(), text);
			}
			catch
			{
				return P_0;
			}
		}

		private string a(string P_0)
		{
			try
			{
				if (!Directory.Exists(P_0))
				{
					return null;
				}
				string[] files = Directory.GetFiles(P_0, "*.dem", SearchOption.AllDirectories);
				if (files == null || files.Length == 0)
				{
					return null;
				}
				string result = null;
				DateTime dateTime = DateTime.MinValue;
				string[] array = files;
				foreach (string text in array)
				{
					try
					{
						DateTime lastWriteTimeUtc = File.GetLastWriteTimeUtc(text);
						if (lastWriteTimeUtc > dateTime)
						{
							dateTime = lastWriteTimeUtc;
							result = text;
						}
					}
					catch
					{
					}
				}
				return result;
			}
			catch
			{
				return null;
			}
		}

		private string E()
		{
			return this.m_A?.a();
		}

		private void A(B P_0, string P_1)
		{
			if (P_0 == null)
			{
				return;
			}
			string text = A(P_0);
			if (string.IsNullOrEmpty(text))
			{
				return;
			}
			try
			{
				P_0.B = text;
			}
			catch
			{
			}
			try
			{
				text = Path.GetFullPath(text);
			}
			catch
			{
			}
			if (!File.Exists(text))
			{
				Debug.LogWarning((object)("[RustDemoPro] FinalizeChunk: demo file missing: " + text));
				return;
			}
			DateTimeOffset dateTimeOffset = D();
			DateTime utcDateTime = dateTimeOffset.UtcDateTime;
			double num = A(dateTimeOffset);
			P_0.B = text;
			i i2 = null;
			List<J> list = this.m_A?.A(P_0.A);
			int num2 = this.m_A?.a(P_0.A) ?? 0;
			int num3 = list?.Count ?? 0;
			B("[RustDemoPro] FinalizeChunk writing meta/events for: " + text);
			B("[RustDemoPro] MetaPath: " + text + ".meta.json | EventsPath: " + text + ".events.json");
			try
			{
				i2 = A(P_0, text, P_1, utcDateTime, num, num3, num2);
				A(text + ".meta.json", i2);
				P_0.B = text;
			}
			catch (Exception arg)
			{
				Debug.LogWarning((object)$"[RustDemoPro] Failed to write meta for '{text}': {arg}");
			}
			try
			{
				if (list != null && list.Count > 1)
				{
					list.Sort((J P_0, J P_1) => P_0.chunkOffsetSeconds.CompareTo(P_1.chunkOffsetSeconds));
				}
				A(text + ".events.json", list ?? new List<J>());
			}
			catch (Exception arg2)
			{
				Debug.LogWarning((object)$"[RustDemoPro] Failed to write events for '{text}': {arg2}");
			}
			try
			{
				this.m_A?.B(P_0.A);
			}
			catch
			{
			}
			try
			{
				this.m_A?.Invoke(new a
				{
					A = P_0.A,
					A = text,
					a = text + ".meta.json",
					B = text + ".events.json",
					A = P_0.A,
					a = utcDateTime,
					b = P_1
				});
			}
			catch
			{
			}
		}

		private string A(B P_0)
		{
			try
			{
				if (P_0 == null)
				{
					return null;
				}
				if (!string.IsNullOrEmpty(P_0.B) && File.Exists(P_0.B))
				{
					return P_0.B;
				}
				string text = E();
				if (!string.IsNullOrEmpty(text))
				{
					string text2 = null;
					if (!string.IsNullOrEmpty(P_0.a))
					{
						try
						{
							string text3 = Path.Combine(text, P_0.a);
							text2 = a(text3);
						}
						catch
						{
						}
					}
					if (string.IsNullOrEmpty(text2))
					{
						text2 = a(text);
					}
					if (!string.IsNullOrEmpty(text2))
					{
						return text2;
					}
				}
				return P_0.B;
			}
			catch
			{
				return P_0?.B;
			}
		}

		private void e()
		{
			if (this.m_b)
			{
				return;
			}
			this.m_b = true;
			try
			{
				string text = null;
				string text2 = null;
				try
				{
					text = E();
				}
				catch
				{
				}
				try
				{
					text2 = Directory.GetCurrentDirectory();
				}
				catch
				{
				}
				B("[RustDemoPro] DemosRoot='" + text + "' cwd='" + text2 + "'");
			}
			catch
			{
			}
		}

		private static void B(string P_0)
		{
			C instance = SingletonComponent<global::A.C>.Instance;
			if (instance == null || instance.Config?.Logging?.Debug != true)
			{
				return;
			}
			try
			{
				Debug.Log((object)P_0);
			}
			catch
			{
			}
		}

		private i A(B P_0, string P_1, string P_2, DateTime P_3, double P_4, int P_5, int P_6)
		{
			i i2 = new i();
			try
			{
				i2.serverIdentity = Server.identity;
			}
			catch
			{
				i2.serverIdentity = null;
			}
			try
			{
				i2.serverName = Server.hostname;
			}
			catch
			{
				i2.serverName = null;
			}
			try
			{
				i2.map = Server.level;
			}
			catch
			{
				i2.map = null;
			}
			i2.networkVersion = d();
			i2.userId = P_0.A;
			i2.steamId = P_0.a;
			i2.playerName = P_0.A;
			i2.demoPath = P_1;
			try
			{
				i2.demoFileName = Path.GetFileName(P_1);
			}
			catch
			{
				i2.demoFileName = P_1;
			}
			i2.chunkReason = P_2;
			i2.startedUtc = P_0.A.ToString("o");
			i2.endedUtc = P_3.ToString("o");
			i2.startedLocal = B(P_0.A);
			i2.endedLocal = B(P_3);
			i2.startedServerSeconds = P_0.A;
			i2.endedServerSeconds = P_4;
			i2.durationSeconds = Math.Max(0.0, (P_3 - P_0.A).TotalSeconds);
			i2.chunkMinutes = 15;
			i2.eventCount = P_5;
			i2.droppedEventCount = P_6;
			return i2;
		}

		private void A<A>(string P_0, A P_1)
		{
			//IL_007e: Unknown result type (might be due to invalid IL or missing references)
			//IL_0083: Unknown result type (might be due to invalid IL or missing references)
			//IL_008c: Expected O, but got Unknown
			string text = P_0 + ".tmp";
			try
			{
				string directoryName = Path.GetDirectoryName(P_0);
				if (!string.IsNullOrEmpty(directoryName))
				{
					Directory.CreateDirectory(directoryName);
				}
				try
				{
					if (File.Exists(text))
					{
						File.Delete(text);
					}
				}
				catch (Exception arg)
				{
					Debug.LogWarning((object)$"[RustDemoPro] WriteJsonAtomic failed: path='{P_0}' tmp='{text}' :: {arg}");
					try
					{
						if (File.Exists(text))
						{
							File.Delete(text);
						}
					}
					catch
					{
					}
				}
				using (FileStream stream = new FileStream(text, FileMode.Create, FileAccess.Write, FileShare.None, 65536))
				{
					using StreamWriter streamWriter = new StreamWriter(stream, N.m_A, 65536);
					JsonTextWriter val = new JsonTextWriter((TextWriter)streamWriter)
					{
						Formatting = (Formatting)0
					};
					try
					{
						lock (N.m_A)
						{
							N.m_A.Serialize((JsonWriter)(object)val, (object)P_1);
						}
					}
					finally
					{
						((IDisposable)val)?.Dispose();
					}
				}
				if (File.Exists(P_0))
				{
					File.Delete(P_0);
				}
				File.Move(text, P_0);
			}
			catch (Exception arg2)
			{
				Debug.LogWarning((object)$"[RustDemoPro] WriteJsonAtomic failed: path='{P_0}' tmp='{text}' :: {arg2}");
				try
				{
					if (File.Exists(text))
					{
						File.Delete(text);
					}
				}
				catch
				{
				}
			}
		}
	}
	internal sealed class n : B
	{
		internal sealed class A
		{
			[CompilerGenerated]
			private DateTime? A;

			[CompilerGenerated]
			private int A;

			[CompilerGenerated]
			private int a;

			[CompilerGenerated]
			private long A;

			public DateTime? LastRunUtc
			{
				[CompilerGenerated]
				get
				{
					return this.A;
				}
				[CompilerGenerated]
				internal set
				{
					this.A = value;
				}
			}

			public int DeletedFiles
			{
				[CompilerGenerated]
				get
				{
					return this.A;
				}
				[CompilerGenerated]
				internal set
				{
					this.A = value;
				}
			}

			public int DeletedFolders
			{
				[CompilerGenerated]
				get
				{
					return a;
				}
				[CompilerGenerated]
				internal set
				{
					a = value;
				}
			}

			public long DeletedBytes
			{
				[CompilerGenerated]
				get
				{
					return A;
				}
				[CompilerGenerated]
				internal set
				{
					A = value;
				}
			}
		}

		internal struct a
		{
			public int A;

			public int a;

			public long A;
		}

		[Serializable]
		[CompilerGenerated]
		private sealed class B
		{
			public static readonly B A = new B();

			public static Comparison<string> A;

			public static Func<string, int> A;

			internal int A(string P_0, string P_1)
			{
				return P_1.Length.CompareTo(P_0.Length);
			}

			internal int A(string P_0)
			{
				return P_0.Length;
			}
		}

		private const string m_A = ".meta.json";

		private const string m_a = ".events.json";

		private readonly N m_A;

		private readonly global::A.a m_A;

		[CompilerGenerated]
		private bool m_A;

		[CompilerGenerated]
		private bool m_a;

		[CompilerGenerated]
		private readonly A m_A = new A();

		public bool Initialized
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			private set
			{
				this.m_A = value;
			}
		}

		public bool Ready
		{
			[CompilerGenerated]
			get
			{
				return this.m_a;
			}
			[CompilerGenerated]
			private set
			{
				this.m_a = value;
			}
		}

		public A Diagnostics
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
		}

		public n(N P_0, global::A.a P_1)
		{
			this.m_A = P_0;
			this.m_A = P_1;
		}

		public void A()
		{
			this.m_A?.a(null);
			Initialized = true;
			Ready = true;
		}

		public int a()
		{
			a a2 = default(a);
			try
			{
				HashSet<string> hashSet = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
				DateTime dateTime = DateTime.UtcNow - TimeSpan.FromHours(1.0);
				DateTime dateTime2 = DateTime.UtcNow - TimeSpan.FromHours(2.0);
				int num = 0;
				List<string> list = new List<string>();
				string text = this.m_A.A(null);
				HashSet<string> hashSet2 = this.m_A?.a();
				foreach (string item in c())
				{
					if (!string.IsNullOrEmpty(item) && Directory.Exists(item) && hashSet.Add(item))
					{
						a a3 = A(item, dateTime, text, hashSet2);
						a a4 = a(item, dateTime2, text, hashSet2);
						a2.A += a3.A;
						a2.a += a4.a;
						a2.A += a3.A + a4.A;
						num += a3.A + a4.a;
						if (D() && (a3.A > 0 || a4.a > 0))
						{
							list.Add($"[RustDemoPro] Storage cleanup: deleted {a3.A} file(s) and {a4.a} empty or stale folder(s). Folder: {item}");
						}
					}
				}
				foreach (string item2 in list)
				{
					Debug.Log((object)item2);
				}
				a(text);
				return num;
			}
			catch
			{
				return 0;
			}
			finally
			{
				A(a2);
			}
		}

		public a A(string P_0, DateTime P_1, string P_2, HashSet<string> P_3)
		{
			a result = default(a);
			try
			{
				foreach (string item in Directory.EnumerateFiles(P_0, "*", SearchOption.AllDirectories))
				{
					try
					{
						if (!string.IsNullOrEmpty(P_2) && this.m_A.a(item, P_2))
						{
							continue;
						}
						string extension = Path.GetExtension(item);
						if (extension.Equals(".dem", StringComparison.OrdinalIgnoreCase) || extension.Equals(".json", StringComparison.OrdinalIgnoreCase))
						{
							string text = b(item);
							if (string.IsNullOrEmpty(text))
							{
								goto IL_0096;
							}
							N obj = this.m_A;
							if (obj == null || !obj.A(text, P_3))
							{
								goto IL_0096;
							}
						}
						goto end_IL_0026;
						IL_0096:
						DateTime dateTime;
						try
						{
							dateTime = File.GetLastWriteTimeUtc(item);
						}
						catch
						{
							dateTime = DateTime.MaxValue;
						}
						if (!(dateTime >= P_1))
						{
							long num = 0L;
							try
							{
								num = new FileInfo(item).Length;
							}
							catch
							{
							}
							int num2 = B(item);
							if (num2 > 0)
							{
								result.A += num2;
								result.A += num;
							}
						}
						end_IL_0026:;
					}
					catch
					{
					}
				}
			}
			catch
			{
			}
			return result;
		}

		public a a(string P_0, DateTime P_1, string P_2, HashSet<string> P_3)
		{
			a result = default(a);
			try
			{
				List<string> list = new List<string>();
				list.AddRange(Directory.EnumerateDirectories(P_0, "*", SearchOption.AllDirectories));
				list.Sort((string P_0, string P_1) => P_1.Length.CompareTo(P_0.Length));
				foreach (string item in list)
				{
					try
					{
						if ((string.IsNullOrEmpty(P_2) || !this.m_A.a(item, P_2)) && !A(item, P_3) && A(item, P_1))
						{
							result.A += A(item);
							Directory.Delete(item, recursive: true);
							result.a++;
						}
					}
					catch
					{
					}
				}
			}
			catch
			{
			}
			return result;
		}

		public long A(string P_0)
		{
			try
			{
				if (string.IsNullOrEmpty(P_0) || !Directory.Exists(P_0))
				{
					return 0L;
				}
				long num = 0L;
				foreach (string item in Directory.EnumerateFiles(P_0, "*", SearchOption.AllDirectories))
				{
					try
					{
						string extension = Path.GetExtension(item);
						if (extension.Equals(".dem", StringComparison.OrdinalIgnoreCase) || extension.Equals(".json", StringComparison.OrdinalIgnoreCase))
						{
							num += new FileInfo(item).Length;
						}
					}
					catch
					{
					}
				}
				return num;
			}
			catch
			{
				return 0L;
			}
		}

		public long B()
		{
			string text = this.m_A.a();
			return A(text);
		}

		public string b()
		{
			return this.m_A.a();
		}

		public long C()
		{
			double valueOrDefault = (SingletonComponent<global::A.C>.Instance?.Config?.StorageLimitGB).GetValueOrDefault();
			if (valueOrDefault <= 0.0)
			{
				return 0L;
			}
			return (long)(valueOrDefault * 1024.0 * 1024.0 * 1024.0);
		}

		private void a(string P_0)
		{
			if (string.IsNullOrEmpty(P_0) || !Directory.Exists(P_0))
			{
				return;
			}
			try
			{
				foreach (string item in from P_0 in Directory.EnumerateDirectories(P_0, "*", SearchOption.AllDirectories)
					orderby P_0.Length descending
					select P_0)
				{
					try
					{
						if (!Directory.EnumerateFileSystemEntries(item).Any())
						{
							Directory.Delete(item);
						}
					}
					catch
					{
					}
				}
			}
			catch
			{
			}
		}

		private IEnumerable<string> c()
		{
			string text = this.m_A.a();
			if (!string.IsNullOrEmpty(text))
			{
				yield return text;
			}
		}

		private static bool A(string P_0, DateTime P_1)
		{
			try
			{
				List<string> list = Directory.EnumerateFileSystemEntries(P_0).ToList();
				if (list.Count == 0)
				{
					return true;
				}
				DateTime dateTime;
				try
				{
					dateTime = Directory.GetLastWriteTimeUtc(P_0);
				}
				catch
				{
					dateTime = DateTime.MinValue;
				}
				foreach (string item in list)
				{
					try
					{
						DateTime dateTime2 = ((!Directory.Exists(item)) ? File.GetLastWriteTimeUtc(item) : Directory.GetLastWriteTimeUtc(item));
						if (dateTime2 > dateTime)
						{
							dateTime = dateTime2;
						}
					}
					catch
					{
					}
				}
				return dateTime < P_1;
			}
			catch
			{
				return false;
			}
		}

		private static int B(string P_0)
		{
			try
			{
				if (!File.Exists(P_0))
				{
					return 0;
				}
				File.Delete(P_0);
				return 1;
			}
			catch
			{
				return 0;
			}
		}

		private static string b(string P_0)
		{
			if (string.IsNullOrEmpty(P_0))
			{
				return null;
			}
			if (P_0.EndsWith(".meta.json", StringComparison.OrdinalIgnoreCase))
			{
				return P_0.Substring(0, P_0.Length - ".meta.json".Length);
			}
			if (P_0.EndsWith(".events.json", StringComparison.OrdinalIgnoreCase))
			{
				return P_0.Substring(0, P_0.Length - ".events.json".Length);
			}
			if (P_0.EndsWith(".dem", StringComparison.OrdinalIgnoreCase))
			{
				return P_0;
			}
			return null;
		}

		private static bool A(string P_0, HashSet<string> P_1)
		{
			if (string.IsNullOrEmpty(P_0) || P_1 == null || P_1.Count == 0)
			{
				return false;
			}
			string value;
			try
			{
				value = Path.GetFullPath(P_0).TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
			}
			catch
			{
				value = P_0.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
			}
			foreach (string item in P_1)
			{
				if (!string.IsNullOrEmpty(item))
				{
					string text;
					try
					{
						text = Path.GetFullPath(item);
					}
					catch
					{
						text = item;
					}
					if (text.StartsWith(value, StringComparison.OrdinalIgnoreCase))
					{
						return true;
					}
				}
			}
			return false;
		}

		private static bool D()
		{
			return (SingletonComponent<global::A.C>.Instance?.Config?.Logging?.Cleanup).GetValueOrDefault();
		}

		private void A(a P_0)
		{
			Diagnostics.LastRunUtc = DateTime.UtcNow;
			Diagnostics.DeletedFiles = P_0.A;
			Diagnostics.DeletedFolders = P_0.a;
			Diagnostics.DeletedBytes = P_0.A;
		}
	}
	internal sealed class O : B
	{
		[Serializable]
		private class A
		{
			public string error;

			public string reason;
		}

		private sealed class a
		{
			public string id;
		}

		private sealed class B : Exception
		{
			[CompilerGenerated]
			private readonly HttpStatusCode A;

			[CompilerGenerated]
			private readonly string A;

			public HttpStatusCode StatusCode
			{
				[CompilerGenerated]
				get
				{
					return this.A;
				}
			}

			public string Body
			{
				[CompilerGenerated]
				get
				{
					return A;
				}
			}

			public B(HttpStatusCode P_0, string P_1)
				: base($"HTTP {(int)P_0}: {P_1}")
			{
				this.A = P_0;
				A = P_1;
			}
		}

		private sealed class b
		{
			public string reportId;

			public string messageId;

			public bool createInFlight;

			public bool pendingFinalUpdate;

			public HashSet<string> uploadedKeys = new HashSet<string>(StringComparer.OrdinalIgnoreCase);

			public HashSet<string> archiveNames = new HashSet<string>(StringComparer.OrdinalIgnoreCase);

			public int totalBundlesUploaded;

			public long totalZipBytesUploaded;

			public DateTime lastUpdatedUtc;
		}

		internal sealed class C
		{
			[CompilerGenerated]
			private DateTime? A;

			[CompilerGenerated]
			private long A;

			[CompilerGenerated]
			private int A;

			[CompilerGenerated]
			private int a;

			public DateTime? LastSweepUtc
			{
				[CompilerGenerated]
				get
				{
					return this.A;
				}
				[CompilerGenerated]
				internal set
				{
					this.A = value;
				}
			}

			public long OutboxBytes
			{
				[CompilerGenerated]
				get
				{
					return this.A;
				}
				[CompilerGenerated]
				internal set
				{
					this.A = value;
				}
			}

			public int ZipCount
			{
				[CompilerGenerated]
				get
				{
					return A;
				}
				[CompilerGenerated]
				internal set
				{
					A = value;
				}
			}

			public int AckCount
			{
				[CompilerGenerated]
				get
				{
					return a;
				}
				[CompilerGenerated]
				internal set
				{
					a = value;
				}
			}
		}

		[Serializable]
		[CompilerGenerated]
		private sealed class c
		{
			public static readonly c A = new c();

			public static Comparison<Tuple<i, string, long>> A;

			public static Comparison<J> A;

			public static Func<K, bool> A;

			public static Func<K, string> A;

			public static Comparison<string> A;

			public static Func<string, int> A;

			public static Func<KeyValuePair<string, b>, DateTime> A;

			public static Func<KeyValuePair<string, b>, string> A;

			internal int A(Tuple<i, string, long> P_0, Tuple<i, string, long> P_1)
			{
				DateTime.TryParse(P_0.Item1.startedUtc, null, DateTimeStyles.RoundtripKind, out var result);
				DateTime.TryParse(P_1.Item1.startedUtc, null, DateTimeStyles.RoundtripKind, out var result2);
				return result.CompareTo(result2);
			}

			internal int A(J P_0, J P_1)
			{
				return P_0.chunkOffsetSeconds.CompareTo(P_1.chunkOffsetSeconds);
			}

			internal bool A(K P_0)
			{
				if (P_0 != null)
				{
					return !string.IsNullOrEmpty(P_0.A);
				}
				return false;
			}

			internal string a(K P_0)
			{
				return P_0.A;
			}

			internal int A(string P_0, string P_1)
			{
				DateTime dateTime = DateTime.MaxValue;
				DateTime value = DateTime.MaxValue;
				try
				{
					dateTime = File.GetLastWriteTimeUtc(P_0);
				}
				catch
				{
				}
				try
				{
					value = File.GetLastWriteTimeUtc(P_1);
				}
				catch
				{
				}
				return dateTime.CompareTo(value);
			}

			internal int A(string P_0)
			{
				return P_0.Length;
			}

			internal DateTime A(KeyValuePair<string, b> P_0)
			{
				return P_0.Value?.lastUpdatedUtc ?? DateTime.MinValue;
			}

			internal string a(KeyValuePair<string, b> P_0)
			{
				return P_0.Key;
			}
		}

		[CompilerGenerated]
		private sealed class D
		{
			public J A;

			internal bool A(J P_0)
			{
				if (P_0.type == "Report")
				{
					return Math.Abs(P_0.chunkOffsetSeconds - this.A.chunkOffsetSeconds) < 0.01;
				}
				return false;
			}
		}

		[CompilerGenerated]
		private sealed class d
		{
			public string A;

			internal bool A(K P_0)
			{
				return string.Equals(P_0.A, this.A, StringComparison.OrdinalIgnoreCase);
			}
		}

		[CompilerGenerated]
		private sealed class E
		{
			public string A;

			internal bool A(K P_0)
			{
				if (P_0 != null)
				{
					return string.Equals(P_0.A, this.A, StringComparison.OrdinalIgnoreCase);
				}
				return false;
			}
		}

		[CompilerGenerated]
		private sealed class e
		{
			public string A;

			internal bool A(K P_0)
			{
				if (P_0 != null)
				{
					return string.Equals(P_0.A, this.A, StringComparison.OrdinalIgnoreCase);
				}
				return false;
			}
		}

		[CompilerGenerated]
		private sealed class F
		{
			public string A;

			internal bool A(K P_0)
			{
				if (P_0?.A != null && !string.IsNullOrWhiteSpace(P_0.A.reportId))
				{
					return string.Equals(this.A, P_0.A.reportId, StringComparison.OrdinalIgnoreCase);
				}
				return false;
			}
		}

		[CompilerGenerated]
		private sealed class f
		{
			public bool A;

			public O A;

			public string A;

			public global::A.c A;

			public K A;

			public global::A.h A;

			public b A;

			public Dictionary<string, int> A;

			public bool a;

			public string a;

			public string B;

			internal async Task A()
			{
				_ = 3;
				try
				{
					if (this.A)
					{
						string text = this.A.E(this.A);
						object obj = this.A.A(this.A, this.A, this.A, this.A, this.A);
						string text2 = await this.A.A(text, obj).ConfigureAwait(continueOnCapturedContext: false);
						a a2 = this.A.e<a>(text2);
						if (a2 != null && !string.IsNullOrWhiteSpace(a2.id))
						{
							lock (this.A.m_a)
							{
								this.A.messageId = a2.id;
								this.A.createInFlight = false;
								this.A.F();
							}
						}
						else
						{
							lock (this.A.m_a)
							{
								this.A.createInFlight = false;
								this.A.F();
							}
						}
						bool pendingFinalUpdate;
						lock (this.A.m_a)
						{
							pendingFinalUpdate = this.A.pendingFinalUpdate;
							this.A.pendingFinalUpdate = false;
							this.A.F();
						}
						if (pendingFinalUpdate && !string.IsNullOrWhiteSpace(a2?.id))
						{
							string text3 = this.A.A(this.A, a2.id);
							object obj2 = this.A.A(this.A, this.A, this.A, this.A, this.A);
							await this.A.a(text3, obj2).ConfigureAwait(continueOnCapturedContext: false);
							if (this.a)
							{
								this.A.d(a);
							}
						}
					}
					else
					{
						if (string.IsNullOrWhiteSpace(B))
						{
							return;
						}
						string text4 = this.A.A(this.A, B);
						object obj3 = this.A.A(this.A, this.A, this.A, this.A, this.A);
						try
						{
							await this.A.a(text4, obj3).ConfigureAwait(continueOnCapturedContext: false);
							if (this.a)
							{
								this.A.d(a);
							}
						}
						catch (B b2) when (b2.StatusCode == HttpStatusCode.NotFound)
						{
							lock (this.A.m_a)
							{
								this.A.messageId = null;
								this.A.createInFlight = false;
								this.A.F();
							}
							string text5 = this.A.E(this.A);
							string text6 = await this.A.A(text5, obj3).ConfigureAwait(continueOnCapturedContext: false);
							a a3 = this.A.e<a>(text6);
							if (a3 != null && !string.IsNullOrWhiteSpace(a3.id))
							{
								lock (this.A.m_a)
								{
									this.A.messageId = a3.id;
									this.A.createInFlight = false;
									this.A.F();
								}
							}
							else
							{
								lock (this.A.m_a)
								{
									this.A.createInFlight = false;
									this.A.F();
								}
							}
						}
						catch (B b3)
						{
							Debug.LogWarning((object)$"[RustDemoPro] Incident webhook edit failed for report {a}: HTTP {(int)b3.StatusCode} {b3.Body}");
						}
					}
				}
				catch (Exception ex)
				{
					lock (this.A.m_a)
					{
						this.A.createInFlight = false;
						this.A.F();
					}
					Debug.LogWarning((object)("[RustDemoPro] Incident webhook embed failed for report " + a + ": " + ex.Message));
				}
			}
		}

		private const string m_A = ".ack";

		private const string m_a = ".meta.json";

		private const string m_B = ".events.json";

		private const int m_A = 5;

		private const double m_A = 256.0;

		private const bool m_A = true;

		private const int m_a = 15;

		private const int m_B = 72;

		private const int m_b = 1;

		private const bool m_a = true;

		private const int m_C = 60;

		private const int m_c = 3600;

		private const int m_D = 24;

		private const int m_d = 30;

		private const string m_b = "HarmonyMods_Data/RustDemoPro/WebhookState.json";

		private const int m_E = 1000;

		private const int m_e = 1;

		private static readonly UTF8Encoding m_A = new UTF8Encoding(encoderShouldEmitUTF8Identifier: false);

		private static readonly object m_A = new object();

		private static readonly HttpClient m_A = new HttpClient
		{
			Timeout = TimeSpan.FromSeconds(30.0)
		};

		private static readonly JsonSerializer m_A = JsonSerializer.Create(new JsonSerializerSettings
		{
			NullValueHandling = (NullValueHandling)1,
			DefaultValueHandling = (DefaultValueHandling)1
		});

		private readonly N m_A;

		private readonly global::A.a m_A;

		private readonly List<K> m_A = new List<K>(32);

		private readonly object m_a = new object();

		private Dictionary<string, b> m_A = new Dictionary<string, b>(StringComparer.OrdinalIgnoreCase);

		[CompilerGenerated]
		private bool m_B;

		[CompilerGenerated]
		private bool m_b;

		[CompilerGenerated]
		private readonly C m_A = new C();

		public bool Initialized
		{
			[CompilerGenerated]
			get
			{
				return this.m_B;
			}
			[CompilerGenerated]
			private set
			{
				this.m_B = value;
			}
		}

		public bool Ready
		{
			[CompilerGenerated]
			get
			{
				return this.m_b;
			}
			[CompilerGenerated]
			private set
			{
				this.m_b = value;
			}
		}

		public C Diagnostics
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
		}

		public O(N P_0, global::A.a P_1)
		{
			this.m_A = P_0;
			this.m_A = P_1;
		}

		public void A()
		{
			this.m_A?.a(null);
			E();
			e();
			Initialized = true;
			Ready = true;
			D();
		}

		public void a()
		{
			if (Ready)
			{
				c("[RustDemoPro] Outbox sweep tick.");
				D();
			}
		}

		public void B()
		{
			if (Ready)
			{
				c($"[RustDemoPro] Upload tick: pending={this.m_A.Count}");
				d();
			}
		}

		public bool A(I P_0, k P_1)
		{
			if (P_0 == null || P_1 == null)
			{
				return false;
			}
			if (string.IsNullOrEmpty(P_0.A) || !File.Exists(P_0.A))
			{
				return false;
			}
			try
			{
				string text = P_0.a;
				if (string.IsNullOrEmpty(text))
				{
					text = P_0.A + ".meta.json";
				}
				i i2 = P_0.A ?? G(text) ?? g(P_0.A);
				if (i2 == null)
				{
					return false;
				}
				if (i2.userId == 0L && P_1.reportedUserId != 0)
				{
					i2.userId = P_1.reportedUserId;
				}
				if (string.IsNullOrEmpty(i2.steamId) && !string.IsNullOrEmpty(P_1.reportedSteamId))
				{
					i2.steamId = P_1.reportedSteamId;
				}
				if (string.IsNullOrEmpty(i2.playerName) && !string.IsNullOrEmpty(P_1.reportedName))
				{
					i2.playerName = P_1.reportedName;
				}
				a(i2, P_0.A, P_1);
				return true;
			}
			catch
			{
				return false;
			}
		}

		private int b()
		{
			int num = 0;
			try
			{
				string text = this.m_A.A(null);
				HashSet<string> hashSet = this.m_A?.a();
				HashSet<string> hashSet2 = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
				foreach (string item2 in C())
				{
					if (string.IsNullOrEmpty(item2) || !Directory.Exists(item2))
					{
						continue;
					}
					foreach (string item3 in Directory.EnumerateFiles(item2, "*.meta.json", SearchOption.AllDirectories))
					{
						try
						{
							string item = item3;
							try
							{
								item = Path.GetFullPath(item3);
							}
							catch
							{
							}
							if (!hashSet2.Add(item) || this.m_A.a(item3, text))
							{
								continue;
							}
							string text2 = item3.Substring(0, item3.Length - ".meta.json".Length);
							if (string.IsNullOrEmpty(text2) || !File.Exists(text2))
							{
								continue;
							}
							N obj2 = this.m_A;
							if (obj2 == null || !obj2.A(text2, hashSet))
							{
								i i2 = G(item3);
								if (i2 == null)
								{
									i2 = g(text2);
								}
								if (i2.reportContext != null)
								{
									a(i2, text2, i2.reportContext);
									num++;
								}
							}
						}
						catch
						{
						}
					}
				}
				num += A(text);
			}
			catch
			{
			}
			return num;
		}

		private void A(i P_0, string P_1, k P_2)
		{
			//IL_04e4: Unknown result type (might be due to invalid IL or missing references)
			//IL_04eb: Expected O, but got Unknown
			if (P_0 == null || string.IsNullOrEmpty(P_1))
			{
				return;
			}
			try
			{
				k k2 = P_2 ?? P_0.reportContext;
				List<Tuple<i, string, long>> list = new List<Tuple<i, string, long>>();
				foreach (string item2 in Directory.EnumerateFiles(P_1, "*.dem", SearchOption.TopDirectoryOnly))
				{
					try
					{
						string text = item2 + ".meta.json";
						if (!File.Exists(text))
						{
							continue;
						}
						i i2 = null;
						try
						{
							i2 = JsonConvert.DeserializeObject<i>(File.ReadAllText(text));
						}
						catch
						{
							i2 = null;
						}
						if (i2 == null)
						{
							i2 = P_0;
						}
						if (k2 != null)
						{
							if (i2.reportContext == null)
							{
								i2.reportContext = k2;
							}
							A(new I
							{
								A = item2,
								A = i2,
								a = text,
								B = item2 + ".events.json",
								A = A(i2.startedUtc, DateTime.UtcNow),
								a = A(i2.endedUtc, DateTime.UtcNow)
							}, i2, k2);
							A(text, i2);
						}
						long num = 0L;
						try
						{
							num += new FileInfo(item2).Length;
						}
						catch
						{
						}
						try
						{
							num += new FileInfo(text).Length;
						}
						catch
						{
						}
						try
						{
							num += new FileInfo(item2 + ".events.json").Length;
						}
						catch
						{
						}
						list.Add(Tuple.Create(i2, item2, num));
					}
					catch
					{
					}
				}
				if (list.Count == 0)
				{
					return;
				}
				list.Sort(delegate(Tuple<i, string, long> P_0, Tuple<i, string, long> P_1)
				{
					DateTime.TryParse(P_0.Item1.startedUtc, null, DateTimeStyles.RoundtripKind, out var result4);
					DateTime.TryParse(P_1.Item1.startedUtc, null, DateTimeStyles.RoundtripKind, out var result5);
					return result4.CompareTo(result5);
				});
				int num2 = Math.Max(1, 5);
				long num3 = (long)Math.Max(1.0, 268435456.0);
				string text2 = this.m_A.A(null);
				if (string.IsNullOrEmpty(text2))
				{
					return;
				}
				Directory.CreateDirectory(text2);
				int val = 1;
				int l = 0;
				while (l < list.Count)
				{
					long num4 = 0L;
					List<Tuple<i, string, long>> list2 = new List<Tuple<i, string, long>>();
					for (; l < list.Count; l++)
					{
						if (list2.Count >= num2)
						{
							break;
						}
						Tuple<i, string, long> tuple = list[l];
						if (list2.Count > 0 && num4 + tuple.Item3 > num3)
						{
							break;
						}
						list2.Add(tuple);
						num4 += tuple.Item3;
					}
					if (list2.Count == 0)
					{
						break;
					}
					i item = list2[0].Item1;
					DateTime.TryParse(item.startedUtc, null, DateTimeStyles.RoundtripKind, out var result);
					string text3 = a(result);
					string text4 = H(item.serverIdentity ?? Server.identity ?? "server");
					string text5 = H(item.steamId ?? P_0.steamId ?? "player");
					string text6 = text4 + "_" + text5 + "_" + text3 + "_";
					val = Math.Max(val, a(text2, text6));
					string text7 = text6 + val + ".zip";
					string text8 = Path.Combine(text2, text7);
					if (File.Exists(text8))
					{
						val++;
						continue;
					}
					global::A.h h2 = new global::A.h();
					h2.archiveName = text7;
					h2.steamId = text5;
					h2.serverId = text4;
					h2.uploadKey = text6.TrimEnd('_') + val;
					h2.compressed = true;
					h2.reportContext = k2;
					global::A.h h3 = h2;
					foreach (Tuple<i, string, long> item3 in list2)
					{
						h3.bundles.Add(new global::A.H
						{
							demoFile = Path.GetFileName(item3.Item2),
							startedUtc = item3.Item1.startedUtc,
							endedUtc = item3.Item1.endedUtc,
							totalBytes = item3.Item3,
							uploadKey = A(item3.Item1)
						});
					}
					if (k2 != null)
					{
						DateTime.TryParse(item.startedUtc, null, DateTimeStyles.RoundtripKind, out var result2);
						DateTime.TryParse(k2.reportedAtUtc, null, DateTimeStyles.RoundtripKind, out var result3);
						if (result2 != DateTime.MinValue && result3 != DateTime.MinValue)
						{
							h3.reportMarkerSeconds = (result3 - result2).TotalSeconds;
						}
						if (h3.reportMarkerSeconds.HasValue && h3.reportContext != null)
						{
							h3.reportContext.reportMarkerSeconds = h3.reportMarkerSeconds;
						}
					}
					try
					{
						using (FileStream fileStream = new FileStream(text8, FileMode.Create, FileAccess.Write, FileShare.None))
						{
							ZipArchive val2 = new ZipArchive((Stream)fileStream, (ZipArchiveMode)1);
							try
							{
								CompressionLevel compressionLevel = CompressionLevel.Optimal;
								foreach (Tuple<i, string, long> item4 in list2)
								{
									A(val2, item4.Item2, "bundles", compressionLevel);
									A(val2, item4.Item2 + ".meta.json", "bundles", compressionLevel);
									A(val2, item4.Item2 + ".events.json", "bundles", compressionLevel);
								}
								string text9 = JsonConvert.SerializeObject((object)h3, (Formatting)1);
								a(val2, "manifest.json", text9, compressionLevel);
							}
							finally
							{
								((IDisposable)val2)?.Dispose();
							}
						}
						foreach (Tuple<i, string, long> item5 in list2)
						{
							h(item5.Item2);
							h(item5.Item2 + ".meta.json");
							h(item5.Item2 + ".events.json");
						}
						c(string.Format("[RustDemoPro] Created archive {0} with {1} bundle(s) ({2}). Ack before cleanup: {3}{4}", text7, list2.Count, A(num4), text8, ".ack"));
						global::A.c c2 = SingletonComponent<global::A.C>.Instance?.Config;
						if (c2 != null)
						{
							global::A.e upload = c2.Upload;
							if (upload != null && upload.Enabled && h3.reportContext != null)
							{
								string text10 = h3.bundles.FirstOrDefault()?.startedUtc ?? item.startedUtc;
								string text11 = h3.bundles.LastOrDefault()?.endedUtc ?? item.endedUtc;
								A(text8, h3.uploadKey, text10, text11, h3.reportContext, h3.reportMarkerSeconds);
							}
						}
					}
					catch (Exception ex)
					{
						Debug.LogWarning((object)("[RustDemoPro] Failed to build archive " + text7 + ": " + ex.Message));
					}
					val++;
				}
			}
			catch
			{
			}
		}

		private void a(i P_0, string P_1, k P_2 = null)
		{
			if (P_0 == null || string.IsNullOrEmpty(P_1))
			{
				return;
			}
			try
			{
				if (P_2 != null)
				{
					A(P_0, P_2);
				}
				if (P_2 == null && P_0.reportContext == null)
				{
					return;
				}
				string text = this.m_A.A(null);
				if (!string.IsNullOrEmpty(text))
				{
					A(P_1 + ".meta.json", P_0);
					string text2 = Path.Combine(text, P_0.steamId ?? P_0.userId.ToString());
					Directory.CreateDirectory(text2);
					string text3 = Path.GetFileName(P_1);
					if (string.IsNullOrEmpty(text3))
					{
						text3 = P_0.demoFileName ?? P_0.userId.ToString();
					}
					string text4 = Path.Combine(text2, text3);
					text4 = j(text4);
					B(P_1, text4);
					B(P_1 + ".meta.json", text4 + ".meta.json");
					B(P_1 + ".events.json", text4 + ".events.json");
					A(P_0, text2, P_2 ?? P_0.reportContext);
				}
			}
			catch
			{
			}
		}

		private static void A(i P_0, k P_1)
		{
			if (P_0 != null && P_1 != null)
			{
				P_0.chunkReason = "report";
				P_0.reportContext = P_1;
			}
		}

		private static void A(ZipArchive P_0, string P_1, string P_2, CompressionLevel P_3)
		{
			if (P_0 == null || string.IsNullOrEmpty(P_1) || !File.Exists(P_1))
			{
				return;
			}
			string text = (string.IsNullOrEmpty(P_2) ? Path.GetFileName(P_1) : (P_2.TrimEnd('/') + "/" + Path.GetFileName(P_1)));
			using Stream destination = P_0.CreateEntry(text, P_3).Open();
			using FileStream fileStream = File.OpenRead(P_1);
			fileStream.CopyTo(destination);
		}

		private static void a(ZipArchive P_0, string P_1, string P_2, CompressionLevel P_3)
		{
			if (P_0 == null || string.IsNullOrEmpty(P_1))
			{
				return;
			}
			using Stream stream = P_0.CreateEntry(P_1, P_3).Open();
			using StreamWriter streamWriter = new StreamWriter(stream, O.m_A);
			streamWriter.Write(P_2 ?? string.Empty);
		}

		private static void A(I P_0, i P_1, k P_2)
		{
			if (P_0 == null || P_1 == null || P_2 == null)
			{
				return;
			}
			try
			{
				string text = P_0.B;
				if (string.IsNullOrEmpty(text))
				{
					return;
				}
				List<J> list = new List<J>();
				if (File.Exists(text))
				{
					try
					{
						list = JsonConvert.DeserializeObject<List<J>>(File.ReadAllText(text)) ?? new List<J>();
					}
					catch
					{
						list = new List<J>();
					}
				}
				J A = O.A(O.A(P_1.startedUtc, DateTime.UtcNow), O.A(P_2.reportedAtUtc, DateTime.UtcNow), P_2);
				if (A != null && !list.Any((J P_0) => P_0.type == "Report" && Math.Abs(P_0.chunkOffsetSeconds - A.chunkOffsetSeconds) < 0.01))
				{
					list.Add(A);
				}
				try
				{
					list.Sort((J P_0, J P_1) => P_0.chunkOffsetSeconds.CompareTo(P_1.chunkOffsetSeconds));
				}
				catch
				{
				}
				P_1.eventCount = list.Count;
				O.A(text, list);
			}
			catch
			{
			}
		}

		private static J A(DateTime P_0, DateTime P_1, k P_2)
		{
			if (P_2 == null)
			{
				return null;
			}
			double totalSeconds = (P_1 - P_0).TotalSeconds;
			DateTimeOffset dateTimeOffset = G();
			return new J
			{
				serverSeconds = A(dateTimeOffset),
				serverTimeLocal = a(dateTimeOffset),
				chunkOffsetSeconds = totalSeconds,
				type = "Report",
				attackerUserId = P_2.reporterUserId,
				attackerName = P_2.reporterName,
				targetUserId = P_2.reportedUserId,
				targetName = P_2.reportedName,
				info = P_2.reason
			};
		}

		private static void A<A>(string P_0, A P_1)
		{
			//IL_0057: Unknown result type (might be due to invalid IL or missing references)
			//IL_005c: Unknown result type (might be due to invalid IL or missing references)
			//IL_0065: Expected O, but got Unknown
			string text = P_0 + ".tmp";
			try
			{
				string directoryName = Path.GetDirectoryName(P_0);
				if (!string.IsNullOrEmpty(directoryName))
				{
					Directory.CreateDirectory(directoryName);
				}
				try
				{
					if (File.Exists(text))
					{
						File.Delete(text);
					}
				}
				catch
				{
				}
				using (FileStream stream = new FileStream(text, FileMode.Create, FileAccess.Write, FileShare.None, 65536))
				{
					using StreamWriter streamWriter = new StreamWriter(stream, O.m_A, 65536);
					JsonTextWriter val = new JsonTextWriter((TextWriter)streamWriter)
					{
						Formatting = (Formatting)0
					};
					try
					{
						lock (O.m_A)
						{
							O.m_A.Serialize((JsonWriter)(object)val, (object)P_1);
						}
					}
					finally
					{
						((IDisposable)val)?.Dispose();
					}
				}
				if (File.Exists(P_0))
				{
					File.Delete(P_0);
				}
				File.Move(text, P_0);
			}
			catch
			{
				try
				{
					if (File.Exists(text))
					{
						File.Delete(text);
					}
				}
				catch
				{
				}
			}
		}

		private IEnumerable<string> C()
		{
			string text = this.m_A.a();
			if (!string.IsNullOrEmpty(text))
			{
				yield return text;
			}
		}

		private void A(string P_0, string P_1, string P_2, string P_3, k P_4, double? P_5)
		{
			if (string.IsNullOrEmpty(P_0) || string.IsNullOrEmpty(P_1) || File.Exists(P_0 + ".ack"))
			{
				return;
			}
			lock (this.m_A)
			{
				if (this.m_A.Any((K P_0) => string.Equals(P_0.A, P_0, StringComparison.OrdinalIgnoreCase)))
				{
					return;
				}
				this.m_A.Add(new K
				{
					A = P_0,
					a = P_1,
					B = P_2,
					b = P_3,
					A = P_4,
					A = P_5,
					A = DateTime.UtcNow
				});
			}
			c("[RustDemoPro] Enqueued upload: " + P_0);
		}

		private void c()
		{
			try
			{
				global::A.c c2 = SingletonComponent<global::A.C>.Instance?.Config;
				if (c2 == null)
				{
					return;
				}
				global::A.e upload = c2.Upload;
				if (upload == null || !upload.Enabled)
				{
					return;
				}
				string text = this.m_A.A(null);
				if (string.IsNullOrEmpty(text) || !Directory.Exists(text))
				{
					return;
				}
				int num = 0;
				foreach (string item in Directory.EnumerateFiles(text, "*.zip", SearchOption.AllDirectories))
				{
					try
					{
						if (!File.Exists(item + ".ack"))
						{
							global::A.h h2 = f(item);
							if (h2 != null && !string.IsNullOrEmpty(h2.uploadKey) && h2.reportContext != null)
							{
								string text2 = h2.bundles.FirstOrDefault()?.startedUtc;
								string text3 = h2.bundles.LastOrDefault()?.endedUtc;
								A(item, h2.uploadKey, text2, text3, h2.reportContext, h2.reportMarkerSeconds);
								num++;
							}
						}
					}
					catch
					{
					}
				}
				if (num > 0)
				{
					c($"[RustDemoPro] Seeded uploads from disk: {num}");
				}
			}
			catch
			{
			}
		}

		private void D()
		{
			string text = this.m_A.A(null);
			try
			{
				if (!string.IsNullOrEmpty(text) && Directory.Exists(text))
				{
					try
					{
						b();
					}
					catch
					{
					}
					try
					{
						c();
					}
					catch
					{
					}
					a(text);
				}
			}
			catch
			{
			}
			finally
			{
				J(text);
			}
		}

		private int A(string P_0)
		{
			if (string.IsNullOrEmpty(P_0) || !Directory.Exists(P_0))
			{
				return 0;
			}
			int num = 0;
			int num2 = 0;
			try
			{
				num = Directory.EnumerateFiles(P_0, "*.zip", SearchOption.TopDirectoryOnly).Count();
			}
			catch
			{
			}
			try
			{
				foreach (string item in Directory.EnumerateDirectories(P_0, "*", SearchOption.TopDirectoryOnly))
				{
					try
					{
						i i2 = null;
						try
						{
							string text = Directory.EnumerateFiles(item, "*.meta.json", SearchOption.TopDirectoryOnly).FirstOrDefault();
							if (!string.IsNullOrEmpty(text))
							{
								i2 = G(text);
							}
						}
						catch
						{
						}
						if (i2 == null)
						{
							i2 = new i
							{
								steamId = Path.GetFileName(item),
								startedUtc = DateTime.UtcNow.ToString("o"),
								endedUtc = DateTime.UtcNow.ToString("o"),
								chunkMinutes = 15
							};
						}
						if (i2.reportContext != null)
						{
							A(i2, item, i2.reportContext);
						}
					}
					catch
					{
					}
				}
			}
			catch
			{
			}
			try
			{
				num2 = Directory.EnumerateFiles(P_0, "*.zip", SearchOption.TopDirectoryOnly).Count();
			}
			catch
			{
			}
			return Math.Max(0, num2 - num);
		}

		private void a(string P_0)
		{
			if (string.IsNullOrEmpty(P_0) || !Directory.Exists(P_0))
			{
				return;
			}
			global::A.c c2 = SingletonComponent<global::A.C>.Instance?.Config;
			if (c2 == null)
			{
				return;
			}
			long num = (long)(c2.Upload?.OutboxMaxGB ?? 0) * 1024L * 1024 * 1024;
			DateTime utcNow = DateTime.UtcNow;
			List<string> list = new List<string>();
			try
			{
				list.AddRange(Directory.EnumerateFiles(P_0, "*.zip", SearchOption.AllDirectories));
			}
			catch
			{
			}
			try
			{
				foreach (string item in Directory.EnumerateFiles(P_0, "*.zip.ack", SearchOption.AllDirectories))
				{
					try
					{
						if (!File.Exists(item.Substring(0, item.Length - ".ack".Length)))
						{
							h(item);
						}
					}
					catch
					{
					}
				}
			}
			catch
			{
			}
			HashSet<string> hashSet = null;
			lock (this.m_A)
			{
				hashSet = new HashSet<string>(from P_0 in this.m_A
					where P_0 != null && !string.IsNullOrEmpty(P_0.A)
					select P_0.A, StringComparer.OrdinalIgnoreCase);
			}
			long num2 = 0L;
			for (int l = 0; l < list.Count; l++)
			{
				string text = list[l];
				try
				{
					string text2 = text + ".ack";
					if (File.Exists(text2))
					{
						h(text);
						h(text2);
						list.RemoveAt(l);
						l--;
					}
					else
					{
						num2 += new FileInfo(text).Length;
					}
				}
				catch
				{
				}
			}
			DateTime dateTime = utcNow.AddHours(-72.0);
			DateTime dateTime2 = utcNow.AddHours(-1.0);
			for (int num3 = 0; num3 < list.Count; num3++)
			{
				string A2 = list[num3];
				try
				{
					if (File.Exists(A2 + ".ack"))
					{
						continue;
					}
					DateTime dateTime3;
					try
					{
						dateTime3 = File.GetLastWriteTimeUtc(A2);
					}
					catch
					{
						dateTime3 = DateTime.MaxValue;
					}
					DateTime dateTime4 = ((hashSet != null && hashSet.Contains(A2)) ? dateTime : dateTime2);
					if (dateTime3 > dateTime4)
					{
						continue;
					}
					long num4 = 0L;
					try
					{
						num4 = new FileInfo(A2).Length;
					}
					catch
					{
					}
					h(A2);
					num2 -= num4;
					lock (this.m_A)
					{
						this.m_A.RemoveAll((K P_0) => P_0 != null && string.Equals(P_0.A, A2, StringComparison.OrdinalIgnoreCase));
					}
					list.RemoveAt(num3);
					num3--;
				}
				catch
				{
				}
			}
			if (num2 <= num)
			{
				return;
			}
			list.Sort(delegate(string P_0, string P_1)
			{
				DateTime dateTime7 = DateTime.MaxValue;
				DateTime value = DateTime.MaxValue;
				try
				{
					dateTime7 = File.GetLastWriteTimeUtc(P_0);
				}
				catch
				{
				}
				try
				{
					value = File.GetLastWriteTimeUtc(P_1);
				}
				catch
				{
				}
				return dateTime7.CompareTo(value);
			});
			for (int num5 = 0; num5 < list.Count; num5++)
			{
				if (num2 <= num)
				{
					break;
				}
				string text3 = list[num5];
				try
				{
					string text4 = text3 + ".ack";
					if (File.Exists(text4))
					{
						long num6 = 0L;
						try
						{
							num6 = new FileInfo(text3).Length;
						}
						catch
						{
						}
						h(text3);
						h(text4);
						num2 -= num6;
						list.RemoveAt(num5);
						num5--;
					}
				}
				catch
				{
				}
			}
			for (int num7 = 0; num7 < list.Count; num7++)
			{
				if (num2 <= num)
				{
					break;
				}
				string A = list[num7];
				try
				{
					if (File.Exists(A + ".ack"))
					{
						continue;
					}
					DateTime dateTime5;
					try
					{
						dateTime5 = File.GetLastWriteTimeUtc(A);
					}
					catch
					{
						dateTime5 = DateTime.MaxValue;
					}
					DateTime dateTime6 = ((hashSet != null && hashSet.Contains(A)) ? dateTime : dateTime2);
					if (dateTime5 > dateTime6)
					{
						continue;
					}
					long num8 = 0L;
					try
					{
						num8 = new FileInfo(A).Length;
					}
					catch
					{
					}
					h(A);
					num2 -= num8;
					lock (this.m_A)
					{
						this.m_A.RemoveAll((K P_0) => P_0 != null && string.Equals(P_0.A, A, StringComparison.OrdinalIgnoreCase));
					}
					list.RemoveAt(num7);
					num7--;
				}
				catch
				{
				}
			}
			if (num2 > num)
			{
				Debug.LogWarning((object)("[RustDemoPro] Outbox still above cap after pruning. Size=" + O.A(num2) + " Cap=" + O.A(num) + ". Consider increasing Upload.OutboxMaxGB."));
			}
			B(P_0);
		}

		private void B(string P_0)
		{
			try
			{
				foreach (string item in from P_0 in Directory.EnumerateDirectories(P_0, "*", SearchOption.AllDirectories)
					orderby P_0.Length descending
					select P_0)
				{
					try
					{
						if (!Directory.EnumerateFileSystemEntries(item).Any())
						{
							Directory.Delete(item);
						}
					}
					catch
					{
					}
				}
			}
			catch
			{
			}
		}

		private void d()
		{
			global::A.c c2 = SingletonComponent<global::A.C>.Instance?.Config;
			if (c2 == null)
			{
				return;
			}
			global::A.e upload = c2.Upload;
			if (upload == null || !upload.Enabled)
			{
				return;
			}
			List<K> list;
			lock (this.m_A)
			{
				list = new List<K>(this.m_A);
			}
			c($"[RustDemoPro] ProcessUploads start: pending={list.Count}");
			foreach (K item in list)
			{
				try
				{
					if (item == null)
					{
						continue;
					}
					if (File.Exists(item.A + ".ack"))
					{
						c("[RustDemoPro] Upload job ack exists; removing: " + item.A);
						lock (this.m_A)
						{
							this.m_A.Remove(item);
						}
					}
					else if (a(item))
					{
						c("[RustDemoPro] Upload job abandoned (retry window exceeded): " + item.A);
						A(item, "Exceeded max retry window");
					}
					else if (item.A > DateTime.UtcNow)
					{
						c($"[RustDemoPro] Upload job delayed until {item.A:o}: {item.A}");
					}
					else
					{
						c("[RustDemoPro] Uploading: " + item.A);
						A(item);
					}
				}
				catch
				{
				}
			}
		}

		private void A(K P_0)
		{
			if (P_0 == null)
			{
				return;
			}
			global::A.c c2 = SingletonComponent<global::A.C>.Instance?.Config;
			if (c2 == null)
			{
				return;
			}
			if (string.IsNullOrEmpty(c2.Upload?.Url) || string.IsNullOrEmpty(c2.Upload?.ApiKey))
			{
				Debug.LogWarning((object)"[RustDemoPro] Upload skipped: missing Upload.Url or Upload.ApiKey in config.");
				c("[RustDemoPro] Upload skipped: missing Upload.Url or Upload.ApiKey in config.");
				return;
			}
			if (P_0.a == default(DateTime))
			{
				P_0.a = DateTime.UtcNow;
			}
			if (!File.Exists(P_0.A))
			{
				Debug.LogWarning((object)("[RustDemoPro] Upload skipped (missing archive): " + P_0.A));
				P_0.A = DateTime.UtcNow.AddSeconds(A(P_0.A));
				return;
			}
			c($"[RustDemoPro] Upload request: {P_0.A} url={c2.Upload.Url} attempt={P_0.A + 1}");
			Dictionary<string, string> dictionary = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
			{
				["X-Api-Key"] = c2.Upload.ApiKey,
				["X-Upload-Key"] = P_0.a ?? Path.GetFileNameWithoutExtension(P_0.A),
				["X-Server-Id"] = f(),
				["X-Chunk-Start"] = P_0.B ?? string.Empty,
				["X-Chunk-End"] = P_0.b ?? string.Empty,
				["Content-Type"] = "application/zip"
			};
			if (P_0.A != null)
			{
				if (!string.IsNullOrEmpty(P_0.A.reportId))
				{
					dictionary["X-Report-Id"] = a(P_0.A.reportId, 128);
				}
				if (!string.IsNullOrEmpty(P_0.A.reportedAtUtc))
				{
					dictionary["X-Report-At"] = a(P_0.A.reportedAtUtc, 64);
				}
				if (!string.IsNullOrEmpty(P_0.A.reporterSteamId))
				{
					dictionary["X-Reporter-SteamId"] = a(P_0.A.reporterSteamId, 32);
				}
				if (!string.IsNullOrEmpty(P_0.A.reporterName))
				{
					dictionary["X-Reporter-Name"] = a(P_0.A.reporterName, 128);
					dictionary["X-Reporter-Name-B64"] = B(P_0.A.reporterName, 512);
				}
				if (!string.IsNullOrEmpty(P_0.A.reportedSteamId))
				{
					dictionary["X-Reported-SteamId"] = a(P_0.A.reportedSteamId, 32);
				}
				if (!string.IsNullOrEmpty(P_0.A.reportedName))
				{
					dictionary["X-Reported-Name"] = a(P_0.A.reportedName, 128);
					dictionary["X-Reported-Name-B64"] = B(P_0.A.reportedName, 512);
				}
				if (!string.IsNullOrEmpty(P_0.A.reason))
				{
					dictionary["X-Report-Reason"] = a(P_0.A.reason, 256);
					dictionary["X-Report-Reason-B64"] = B(P_0.A.reason, 1024);
				}
				if (P_0.A.captureWindowBeforeMinutes > 0)
				{
					dictionary["X-Capture-Before-Minutes"] = P_0.A.captureWindowBeforeMinutes.ToString(CultureInfo.InvariantCulture);
				}
				if (P_0.A.captureWindowAfterMinutes > 0)
				{
					dictionary["X-Capture-After-Minutes"] = P_0.A.captureWindowAfterMinutes.ToString(CultureInfo.InvariantCulture);
				}
				if (!string.IsNullOrEmpty(P_0.A.captureWindowStartUtc))
				{
					dictionary["X-Capture-Start-Utc"] = a(P_0.A.captureWindowStartUtc, 64);
				}
				if (!string.IsNullOrEmpty(P_0.A.captureWindowEndUtc))
				{
					dictionary["X-Capture-End-Utc"] = a(P_0.A.captureWindowEndUtc, 64);
				}
				if (P_0.A.HasValue)
				{
					dictionary["X-Report-Marker-Seconds"] = P_0.A.Value.ToString(CultureInfo.InvariantCulture);
				}
			}
			string url = c2.Upload.Url;
			P_0.A++;
			P_0.A = DateTime.UtcNow.AddSeconds(A(P_0.A));
			A(P_0, url, dictionary);
		}

		private async Task A(K P_0, string P_1, Dictionary<string, string> P_2)
		{
			_ = 1;
			try
			{
				HttpRequestMessage val = new HttpRequestMessage(HttpMethod.Post, P_1);
				try
				{
					foreach (KeyValuePair<string, string> item in P_2)
					{
						if (!item.Key.Equals("Content-Type", StringComparison.OrdinalIgnoreCase))
						{
							((HttpHeaders)val.Headers).TryAddWithoutValidation(item.Key, item.Value);
						}
					}
					using FileStream fileStream = new FileStream(P_0.A, FileMode.Open, FileAccess.Read, FileShare.Read, 65536);
					StreamContent val2 = new StreamContent((Stream)fileStream, 65536);
					try
					{
						((HttpContent)val2).Headers.ContentType = new MediaTypeHeaderValue("application/zip");
						val.Content = (HttpContent)(object)val2;
						HttpResponseMessage val3 = await O.m_A.SendAsync(val, (HttpCompletionOption)1).ConfigureAwait(continueOnCapturedContext: false);
						try
						{
							int statusCode = (int)val3.StatusCode;
							string text = null;
							try
							{
								text = await val3.Content.ReadAsStringAsync().ConfigureAwait(continueOnCapturedContext: false);
							}
							catch
							{
							}
							c($"[RustDemoPro] Upload response: {statusCode} for {P_0.A}");
							A(P_0, statusCode, text);
						}
						finally
						{
							((IDisposable)val3)?.Dispose();
						}
					}
					finally
					{
						((IDisposable)val2)?.Dispose();
					}
				}
				finally
				{
					((IDisposable)val)?.Dispose();
				}
			}
			catch (Exception ex)
			{
				string text2 = ex.GetType().Name + ": " + ex.Message;
				c("[RustDemoPro] Upload request exception: " + text2 + " for " + P_0?.A);
				A(P_0, 0, text2);
			}
		}

		private void A(K P_0, int P_1, string P_2)
		{
			try
			{
				switch (P_1)
				{
				case 0:
					Debug.LogWarning((object)("[RustDemoPro] Upload failed (no response): " + P_0.A + " :: " + P_2));
					c("[RustDemoPro] Upload failed (no response): " + P_0.A + " :: " + P_2);
					break;
				case 200:
				case 201:
				case 202:
				case 203:
				case 204:
				case 205:
				case 206:
				case 207:
				case 208:
				case 209:
				case 210:
				case 211:
				case 212:
				case 213:
				case 214:
				case 215:
				case 216:
				case 217:
				case 218:
				case 219:
				case 220:
				case 221:
				case 222:
				case 223:
				case 224:
				case 225:
				case 226:
				case 227:
				case 228:
				case 229:
				case 230:
				case 231:
				case 232:
				case 233:
				case 234:
				case 235:
				case 236:
				case 237:
				case 238:
				case 239:
				case 240:
				case 241:
				case 242:
				case 243:
				case 244:
				case 245:
				case 246:
				case 247:
				case 248:
				case 249:
				case 250:
				case 251:
				case 252:
				case 253:
				case 254:
				case 255:
				case 256:
				case 257:
				case 258:
				case 259:
				case 260:
				case 261:
				case 262:
				case 263:
				case 264:
				case 265:
				case 266:
				case 267:
				case 268:
				case 269:
				case 270:
				case 271:
				case 272:
				case 273:
				case 274:
				case 275:
				case 276:
				case 277:
				case 278:
				case 279:
				case 280:
				case 281:
				case 282:
				case 283:
				case 284:
				case 285:
				case 286:
				case 287:
				case 288:
				case 289:
				case 290:
				case 291:
				case 292:
				case 293:
				case 294:
				case 295:
				case 296:
				case 297:
				case 298:
				case 299:
				{
					bool flag = b(P_2);
					string text2 = P_0.A + ".ack";
					try
					{
						File.WriteAllText(text2, "ack");
					}
					catch
					{
					}
					lock (this.m_A)
					{
						this.m_A.Remove(P_0);
					}
					if (flag)
					{
						B(P_0);
					}
					else
					{
						bool flag2 = b(P_0);
						try
						{
							A(P_0, flag2);
						}
						catch
						{
						}
					}
					if (I(P_0.A))
					{
						h(text2);
					}
					if (flag)
					{
						c("[RustDemoPro] Upload ignored as duplicate for " + P_0.A + " (key " + P_0.a + "); cleared local job.");
					}
					else
					{
						c("[RustDemoPro] Upload completed for " + P_0.A + " (key " + P_0.a + "); deleted archive.");
					}
					break;
				}
				default:
					if (P_1 == 403 && !string.IsNullOrWhiteSpace(P_2) && P_2.IndexOf("api_key_inactive", StringComparison.OrdinalIgnoreCase) >= 0)
					{
						string text = C(P_2) ?? "inactive";
						P_0.A = DateTime.UtcNow.AddHours(24.0);
						Debug.LogWarning((object)("[RustDemoPro] API key inactive: " + text + ". Upload paused."));
						c("[RustDemoPro] API key inactive: " + text + ". Upload paused.");
					}
					else
					{
						Debug.LogWarning((object)$"[RustDemoPro] Upload failed ({P_1}) for {P_0.A}: {P_2}");
						c($"[RustDemoPro] Upload failed ({P_1}) for {P_0.A}: {P_2}");
					}
					break;
				}
			}
			catch
			{
			}
		}

		private bool b(string P_0)
		{
			if (string.IsNullOrWhiteSpace(P_0))
			{
				return false;
			}
			if (P_0.IndexOf("duplicate-report", StringComparison.OrdinalIgnoreCase) >= 0)
			{
				return true;
			}
			if (P_0.IndexOf("\"ignored\":true", StringComparison.OrdinalIgnoreCase) >= 0)
			{
				return true;
			}
			return P_0.IndexOf("uploads/ignored", StringComparison.OrdinalIgnoreCase) >= 0;
		}

		private bool a(K P_0)
		{
			if (P_0 == null)
			{
				return false;
			}
			if (P_0.a == default(DateTime))
			{
				return false;
			}
			return DateTime.UtcNow - P_0.a > TimeSpan.FromHours(24.0);
		}

		private void A(K P_0, string P_1)
		{
			if (P_0 != null)
			{
				lock (this.m_A)
				{
					this.m_A.Remove(P_0);
				}
				i(P_0.A);
				if (I(P_0.A))
				{
					h(P_0.A + ".ack");
				}
				B(P_0);
				Debug.LogWarning((object)("[RustDemoPro] Upload abandoned (" + P_1 + "): " + P_0.A));
			}
		}

		private void B(K P_0)
		{
			if (P_0 == null)
			{
				return;
			}
			string text = ((P_0.A != null) ? P_0.A.reportId : null);
			if (string.IsNullOrWhiteSpace(text))
			{
				text = P_0.a;
			}
			if (string.IsNullOrWhiteSpace(text))
			{
				return;
			}
			lock (this.m_a)
			{
				if (this.m_A.Remove(text))
				{
					F();
				}
			}
		}

		private static string C(string P_0)
		{
			if (string.IsNullOrWhiteSpace(P_0))
			{
				return null;
			}
			try
			{
				A a2 = JsonConvert.DeserializeObject<A>(P_0);
				if (a2 != null && !string.IsNullOrEmpty(a2.reason))
				{
					return a2.reason;
				}
			}
			catch
			{
			}
			return null;
		}

		private static void c(string P_0)
		{
			global::A.C instance = SingletonComponent<global::A.C>.Instance;
			if (instance == null || instance.Config?.Logging?.Debug != true)
			{
				return;
			}
			try
			{
				Debug.Log((object)P_0);
			}
			catch
			{
			}
		}

		private void E()
		{
			try
			{
				ServicePointManager.SecurityProtocol |= SecurityProtocolType.Tls12;
			}
			catch
			{
			}
		}

		private void e()
		{
			try
			{
				if (!File.Exists("HarmonyMods_Data/RustDemoPro/WebhookState.json"))
				{
					lock (this.m_a)
					{
						this.m_A = new Dictionary<string, b>(StringComparer.OrdinalIgnoreCase);
						return;
					}
				}
				Dictionary<string, b> dictionary = JsonConvert.DeserializeObject<Dictionary<string, b>>(File.ReadAllText("HarmonyMods_Data/RustDemoPro/WebhookState.json"));
				Dictionary<string, b> dictionary2 = new Dictionary<string, b>(StringComparer.OrdinalIgnoreCase);
				if (dictionary != null)
				{
					foreach (KeyValuePair<string, b> item in dictionary)
					{
						if (!string.IsNullOrWhiteSpace(item.Key) && item.Value != null)
						{
							b value = item.Value;
							value.reportId = (string.IsNullOrWhiteSpace(value.reportId) ? item.Key : value.reportId);
							if (value.uploadedKeys == null)
							{
								value.uploadedKeys = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
							}
							else if (value.uploadedKeys.Comparer != StringComparer.OrdinalIgnoreCase)
							{
								value.uploadedKeys = new HashSet<string>(value.uploadedKeys, StringComparer.OrdinalIgnoreCase);
							}
							if (value.archiveNames == null)
							{
								value.archiveNames = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
							}
							else if (value.archiveNames.Comparer != StringComparer.OrdinalIgnoreCase)
							{
								value.archiveNames = new HashSet<string>(value.archiveNames, StringComparer.OrdinalIgnoreCase);
							}
							dictionary2[item.Key] = value;
						}
					}
				}
				bool flag = A(dictionary2);
				lock (this.m_a)
				{
					this.m_A = dictionary2;
				}
				if (flag)
				{
					F();
				}
			}
			catch
			{
				lock (this.m_a)
				{
					this.m_A = new Dictionary<string, b>(StringComparer.OrdinalIgnoreCase);
				}
			}
		}

		private void F()
		{
			try
			{
				Dictionary<string, b> dictionary;
				lock (this.m_a)
				{
					A(this.m_A);
					dictionary = new Dictionary<string, b>(this.m_A, StringComparer.OrdinalIgnoreCase);
				}
				string directoryName = Path.GetDirectoryName("HarmonyMods_Data/RustDemoPro/WebhookState.json");
				if (!string.IsNullOrEmpty(directoryName))
				{
					Directory.CreateDirectory(directoryName);
				}
				A("HarmonyMods_Data/RustDemoPro/WebhookState.json", dictionary);
			}
			catch
			{
			}
		}

		private static bool A(Dictionary<string, b> P_0)
		{
			if (P_0 == null || P_0.Count == 0)
			{
				return false;
			}
			DateTime dateTime = DateTime.UtcNow.AddHours(-1.0);
			List<string> list = new List<string>();
			bool result = false;
			foreach (KeyValuePair<string, b> item in P_0)
			{
				b value = item.Value;
				if (value == null)
				{
					list.Add(item.Key);
				}
				else if (value.lastUpdatedUtc != default(DateTime) && value.lastUpdatedUtc < dateTime)
				{
					list.Add(item.Key);
				}
				else
				{
					if (!(value.lastUpdatedUtc == default(DateTime)))
					{
						continue;
					}
					bool flag = value.createInFlight || value.pendingFinalUpdate;
					if (A(value.reportId ?? item.Key, out var dateTime2))
					{
						if (dateTime2 < dateTime)
						{
							list.Add(item.Key);
							continue;
						}
						value.lastUpdatedUtc = dateTime2;
						result = true;
					}
					else if (!flag)
					{
						list.Add(item.Key);
						continue;
					}
					if (!(!string.IsNullOrWhiteSpace(value.messageId) || (value.uploadedKeys != null && value.uploadedKeys.Count > 0) || (value.archiveNames != null && value.archiveNames.Count > 0) || flag))
					{
						list.Add(item.Key);
					}
				}
			}
			foreach (string item2 in list)
			{
				P_0.Remove(item2);
				result = true;
			}
			if (P_0.Count <= 1000)
			{
				return result;
			}
			int count = P_0.Count - 1000;
			foreach (string item3 in (from P_0 in P_0.OrderBy((KeyValuePair<string, b> P_0) => P_0.Value?.lastUpdatedUtc ?? DateTime.MinValue).Take(count)
				select P_0.Key).ToList())
			{
				P_0.Remove(item3);
				result = true;
			}
			return result;
		}

		private static bool A(string P_0, out DateTime P_1)
		{
			P_1 = default(DateTime);
			if (string.IsNullOrWhiteSpace(P_0))
			{
				return false;
			}
			if (!P_0.StartsWith("report-", StringComparison.OrdinalIgnoreCase))
			{
				return false;
			}
			int num = P_0.IndexOf('-', "report-".Length);
			if (num < 0)
			{
				return false;
			}
			return DateTime.TryParseExact(P_0.Substring("report-".Length, num - "report-".Length), "yyyyMMdd'T'HHmmssfff'Z'", CultureInfo.InvariantCulture, DateTimeStyles.AdjustToUniversal | DateTimeStyles.AssumeUniversal, out P_1);
		}

		private bool D(string P_0)
		{
			if (string.IsNullOrWhiteSpace(P_0))
			{
				return false;
			}
			lock (this.m_A)
			{
				return this.m_A.Any((K P_0) => P_0?.A != null && !string.IsNullOrWhiteSpace(P_0.A.reportId) && string.Equals(P_0, P_0.A.reportId, StringComparison.OrdinalIgnoreCase));
			}
		}

		private bool b(K P_0)
		{
			string text = P_0?.A?.reportId;
			if (string.IsNullOrWhiteSpace(text))
			{
				return false;
			}
			return !D(text);
		}

		private void d(string P_0)
		{
			if (string.IsNullOrWhiteSpace(P_0) || D(P_0))
			{
				return;
			}
			lock (this.m_a)
			{
				if (this.m_A.TryGetValue(P_0, out var value) && value != null)
				{
					value.createInFlight = false;
					value.pendingFinalUpdate = false;
					value.lastUpdatedUtc = DateTime.UtcNow;
				}
			}
			F();
		}

		private void A(K P_0, bool P_1)
		{
			if (P_0 == null || P_0.A == null)
			{
				return;
			}
			global::A.c A6 = SingletonComponent<global::A.C>.Instance?.Config;
			if (A6 == null)
			{
				return;
			}
			string A5 = A6.Upload?.IncidentWebhook?.Url;
			if (string.IsNullOrWhiteSpace(A5))
			{
				return;
			}
			string a2 = P_0.A.reportId;
			if (string.IsNullOrWhiteSpace(a2))
			{
				a2 = P_0.a ?? Guid.NewGuid().ToString("N");
			}
			DateTime dateTime = DateTime.UtcNow.AddHours(-1.0);
			DateTime dateTime2;
			bool flag = O.A(a2, out dateTime2) && dateTime2 < dateTime;
			global::A.h A4 = null;
			try
			{
				A4 = f(P_0.A);
			}
			catch
			{
				A4 = null;
			}
			bool flag2 = true;
			bool flag3 = false;
			bool flag4 = false;
			b A3;
			lock (this.m_a)
			{
				if (!this.m_A.TryGetValue(a2, out A3) || A3 == null)
				{
					A3 = new b
					{
						reportId = a2
					};
					this.m_A[a2] = A3;
				}
				if (flag)
				{
					A3.lastUpdatedUtc = dateTime2;
					A3.createInFlight = false;
					A3.pendingFinalUpdate = false;
					flag4 = true;
				}
				if (!flag4)
				{
					if (A3.uploadedKeys == null)
					{
						A3.uploadedKeys = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
					}
					if (A3.archiveNames == null)
					{
						A3.archiveNames = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
					}
					if (!string.IsNullOrEmpty(P_0.a))
					{
						flag2 = A3.uploadedKeys.Add(P_0.a);
					}
					string text = ((A4 != null && !string.IsNullOrEmpty(A4.archiveName)) ? A4.archiveName : (string.IsNullOrEmpty(P_0.A) ? null : Path.GetFileName(P_0.A)));
					if (!string.IsNullOrEmpty(text))
					{
						flag3 = A3.archiveNames.Add(text);
					}
					int num = 0;
					try
					{
						num = (A4?.bundles?.Count).GetValueOrDefault();
					}
					catch
					{
						num = 0;
					}
					long val = 0L;
					try
					{
						if (!string.IsNullOrEmpty(P_0.A) && File.Exists(P_0.A))
						{
							val = new FileInfo(P_0.A).Length;
						}
					}
					catch
					{
					}
					if (flag2)
					{
						A3.totalBundlesUploaded += Math.Max(0, num);
						A3.totalZipBytesUploaded += Math.Max(0L, val);
					}
				}
			}
			if (flag4)
			{
				F();
				return;
			}
			Dictionary<string, int> A2 = null;
			global::A.e upload = A6.Upload;
			if (upload != null && upload.IncidentWebhook?.IncludeEventSummary == true)
			{
				try
				{
					A2 = F(P_0.A);
				}
				catch
				{
					A2 = null;
				}
			}
			DateTime utcNow = DateTime.UtcNow;
			bool A = false;
			string B = null;
			lock (this.m_a)
			{
				if (!P_1 && A3.lastUpdatedUtc != default(DateTime) && utcNow - A3.lastUpdatedUtc < TimeSpan.FromSeconds(2.0))
				{
					F();
					return;
				}
				if (!flag2 && !flag3)
				{
					A3.lastUpdatedUtc = utcNow;
					F();
					return;
				}
				if (string.IsNullOrWhiteSpace(A3.messageId))
				{
					if (A3.createInFlight)
					{
						if (P_1)
						{
							A3.pendingFinalUpdate = true;
						}
						A3.lastUpdatedUtc = utcNow;
						F();
						return;
					}
					A3.createInFlight = true;
					A = true;
				}
				else
				{
					B = A3.messageId;
				}
				A3.lastUpdatedUtc = utcNow;
				F();
			}
			Task.Run(async delegate
			{
				_ = 3;
				try
				{
					if (A)
					{
						string text2 = E(A5);
						object obj5 = this.A(A6, P_0, A4, A3, A2);
						string text3 = await this.A(text2, obj5).ConfigureAwait(continueOnCapturedContext: false);
						a a3 = e<a>(text3);
						if (a3 != null && !string.IsNullOrWhiteSpace(a3.id))
						{
							lock (this.m_a)
							{
								A3.messageId = a3.id;
								A3.createInFlight = false;
								F();
							}
						}
						else
						{
							lock (this.m_a)
							{
								A3.createInFlight = false;
								F();
							}
						}
						bool pendingFinalUpdate;
						lock (this.m_a)
						{
							pendingFinalUpdate = A3.pendingFinalUpdate;
							A3.pendingFinalUpdate = false;
							F();
						}
						if (pendingFinalUpdate && !string.IsNullOrWhiteSpace(a3?.id))
						{
							string text4 = this.A(A5, a3.id);
							object obj6 = this.A(A6, P_0, A4, A3, A2);
							await a(text4, obj6).ConfigureAwait(continueOnCapturedContext: false);
							if (P_1)
							{
								d(a2);
							}
						}
					}
					else if (!string.IsNullOrWhiteSpace(B))
					{
						string text5 = this.A(A5, B);
						object obj7 = this.A(A6, P_0, A4, A3, A2);
						try
						{
							await a(text5, obj7).ConfigureAwait(continueOnCapturedContext: false);
							if (P_1)
							{
								d(a2);
							}
						}
						catch (B b2) when (b2.StatusCode == HttpStatusCode.NotFound)
						{
							lock (this.m_a)
							{
								A3.messageId = null;
								A3.createInFlight = false;
								F();
							}
							string text6 = E(A5);
							string text7 = await this.A(text6, obj7).ConfigureAwait(continueOnCapturedContext: false);
							a a4 = e<a>(text7);
							if (a4 != null && !string.IsNullOrWhiteSpace(a4.id))
							{
								lock (this.m_a)
								{
									A3.messageId = a4.id;
									A3.createInFlight = false;
									F();
								}
							}
							else
							{
								lock (this.m_a)
								{
									A3.createInFlight = false;
									F();
								}
							}
						}
						catch (B b3)
						{
							Debug.LogWarning((object)$"[RustDemoPro] Incident webhook edit failed for report {a2}: HTTP {(int)b3.StatusCode} {b3.Body}");
						}
					}
				}
				catch (Exception ex)
				{
					lock (this.m_a)
					{
						A3.createInFlight = false;
						F();
					}
					Debug.LogWarning((object)("[RustDemoPro] Incident webhook embed failed for report " + a2 + ": " + ex.Message));
				}
			});
		}

		private string E(string P_0)
		{
			if (string.IsNullOrWhiteSpace(P_0))
			{
				return P_0;
			}
			return P_0.Split('?')[0].TrimEnd('/') + "?wait=true";
		}

		private string A(string P_0, string P_1)
		{
			return P_0.Split('?')[0].TrimEnd('/') + "/messages/" + P_1;
		}

		private async Task<string> A(string P_0, object P_1)
		{
			string text = JsonConvert.SerializeObject(P_1, new JsonSerializerSettings
			{
				NullValueHandling = (NullValueHandling)1
			});
			HttpRequestMessage val = new HttpRequestMessage(HttpMethod.Post, P_0);
			try
			{
				val.Content = (HttpContent)new StringContent(text, Encoding.UTF8, "application/json");
				HttpResponseMessage val2 = await O.m_A.SendAsync(val).ConfigureAwait(continueOnCapturedContext: false);
				try
				{
					string text2 = await val2.Content.ReadAsStringAsync().ConfigureAwait(continueOnCapturedContext: false);
					if (!val2.IsSuccessStatusCode)
					{
						throw new B(val2.StatusCode, text2);
					}
					return text2;
				}
				finally
				{
					((IDisposable)val2)?.Dispose();
				}
			}
			finally
			{
				((IDisposable)val)?.Dispose();
			}
		}

		private async Task a(string P_0, object P_1)
		{
			string text = JsonConvert.SerializeObject(P_1, new JsonSerializerSettings
			{
				NullValueHandling = (NullValueHandling)1
			});
			HttpMethod val = new HttpMethod("PATCH");
			HttpRequestMessage val2 = new HttpRequestMessage(val, P_0);
			try
			{
				val2.Content = (HttpContent)new StringContent(text, Encoding.UTF8, "application/json");
				HttpResponseMessage val3 = await O.m_A.SendAsync(val2).ConfigureAwait(continueOnCapturedContext: false);
				try
				{
					string text2 = await val3.Content.ReadAsStringAsync().ConfigureAwait(continueOnCapturedContext: false);
					if (!val3.IsSuccessStatusCode)
					{
						throw new B(val3.StatusCode, text2);
					}
				}
				finally
				{
					((IDisposable)val3)?.Dispose();
				}
			}
			finally
			{
				((IDisposable)val2)?.Dispose();
			}
		}

		private A e<A>(string P_0) where A : class
		{
			if (string.IsNullOrWhiteSpace(P_0))
			{
				return null;
			}
			try
			{
				return JsonConvert.DeserializeObject<A>(P_0);
			}
			catch
			{
				return null;
			}
		}

		private object A(global::A.c P_0, K P_1, global::A.h P_2, b P_3, Dictionary<string, int> P_4)
		{
			k k2 = P_1.A;
			string text = k2.reportId ?? P_3.reportId ?? P_1.a ?? "report";
			string text2 = (P_0.Upload?.PortalBaseUrl ?? "").TrimEnd('/');
			string value = (string.IsNullOrWhiteSpace(text2) ? null : (text2 + "/portal.php?report=" + Uri.EscapeDataString(text)));
			List<string> list = new List<string>();
			List<string> list2 = new List<string>();
			List<string> list3 = new List<string>();
			List<object> list4 = new List<object>();
			if (!string.IsNullOrWhiteSpace(k2.reason))
			{
				list.Add(k2.reason);
			}
			if (!string.IsNullOrWhiteSpace(k2.subject))
			{
				list.Add(k2.subject);
			}
			if (!string.IsNullOrWhiteSpace(k2.type))
			{
				list.Add("Type: " + k2.type);
			}
			if (!string.IsNullOrWhiteSpace(k2.message))
			{
				list.Add(k2.message);
			}
			string text3 = ((list.Count > 0) ? string.Join("\n", list.Distinct()) : "—");
			if (P_3.archiveNames != null)
			{
				list2.AddRange(P_3.archiveNames);
			}
			list2.Sort(StringComparer.OrdinalIgnoreCase);
			int num = Math.Min(8, list2.Count);
			for (int l = 0; l < num; l++)
			{
				list3.Add("• `" + list2[l] + "`");
			}
			if (list2.Count > 8)
			{
				list3.Add($"• …and {list2.Count - 8} more");
			}
			string text4 = ((list3.Count > 0) ? string.Join("\n", list3) : "—");
			string text5 = null;
			if (P_4 != null && P_4.Count > 0)
			{
				int value2;
				int num2 = (P_4.TryGetValue("Hit", out value2) ? value2 : 0);
				int value3;
				int num3 = (P_4.TryGetValue("Kill", out value3) ? value3 : 0);
				int value4;
				int num4 = (P_4.TryGetValue("Death", out value4) ? value4 : 0);
				int value5;
				int num5 = (P_4.TryGetValue("Report", out value5) ? value5 : 0);
				if (k2 != null && k2.reportCount > 0 && num5 < k2.reportCount)
				{
					num5 = k2.reportCount;
				}
				text5 = $"Hits: **{num2}** • Kills: **{num3}** • Deaths: **{num4}** • Report markers: **{num5}**";
			}
			string value6 = null;
			try
			{
				if (DateTime.TryParse(k2.reportedAtUtc, null, DateTimeStyles.RoundtripKind, out var result))
				{
					value6 = result.ToUniversalTime().ToString("o");
				}
			}
			catch
			{
			}
			Dictionary<string, object> dictionary = new Dictionary<string, object>
			{
				["title"] = "\ud83d\udea8 F7 Report Captured",
				["url"] = value,
				["color"] = P_0.Upload.IncidentWebhook.Color,
				["timestamp"] = value6,
				["description"] = "**Report ID:** `" + text + "`\n" + $"**Status:** Archives uploaded: **{P_3.archiveNames?.Count ?? 0}**",
				["fields"] = A(P_0, k2, P_1, P_3, text3, text4, text5),
				["footer"] = new Dictionary<string, object> { ["text"] = "Demo Pro • RustDemoPro" }
			};
			if (!string.IsNullOrWhiteSpace(value))
			{
				list4.Add(new Dictionary<string, object>
				{
					["type"] = 1,
					["components"] = new object[1]
					{
						new Dictionary<string, object>
						{
							["type"] = 2,
							["style"] = 5,
							["label"] = "Open in Demo Pro",
							["url"] = value
						}
					}
				});
			}
			Dictionary<string, object> dictionary2 = new Dictionary<string, object>();
			dictionary2["username"] = (string.IsNullOrWhiteSpace(P_0.Upload.IncidentWebhook.Username) ? "Demo Pro" : P_0.Upload.IncidentWebhook.Username);
			dictionary2["avatar_url"] = (string.IsNullOrWhiteSpace(P_0.Upload.IncidentWebhook.AvatarUrl) ? null : P_0.Upload.IncidentWebhook.AvatarUrl);
			dictionary2["allowed_mentions"] = new Dictionary<string, object> { ["parse"] = new string[0] };
			dictionary2["embeds"] = new object[1] { dictionary };
			dictionary2["components"] = ((list4.Count > 0) ? list4.ToArray() : null);
			return dictionary2;
		}

		private List<Dictionary<string, object>> A(global::A.c P_0, k P_1, K P_2, b P_3, string P_4, string P_5, string P_6)
		{
			List<Dictionary<string, object>> list = new List<Dictionary<string, object>>();
			list.Add(new Dictionary<string, object>
			{
				["name"] = "Server",
				["value"] = "`" + f() + "`\n" + (Server.hostname ?? "Rust Server"),
				["inline"] = true
			});
			string value = A(P_0);
			if (!string.IsNullOrWhiteSpace(value))
			{
				list.Add(new Dictionary<string, object>
				{
					["name"] = "Mode",
					["value"] = value,
					["inline"] = true
				});
			}
			list.Add(new Dictionary<string, object>
			{
				["name"] = "Reported Player",
				["value"] = "**" + (P_1.reportedName ?? "unknown") + "**\n`" + (P_1.reportedSteamId ?? "—") + "`",
				["inline"] = true
			});
			list.Add(new Dictionary<string, object>
			{
				["name"] = "Reporter",
				["value"] = "**" + (P_1.reporterName ?? "unknown") + "**\n`" + (P_1.reporterSteamId ?? "—") + "`",
				["inline"] = true
			});
			list.Add(new Dictionary<string, object>
			{
				["name"] = "Reason / Details",
				["value"] = A(P_4, 900),
				["inline"] = false
			});
			string value2 = "—";
			if (!string.IsNullOrWhiteSpace(P_1.captureWindowStartUtc) || !string.IsNullOrWhiteSpace(P_1.captureWindowEndUtc))
			{
				value2 = ((P_1.captureWindowBeforeMinutes > 0) ? P_1.captureWindowBeforeMinutes.ToString() : "30") + "m before → " + ((P_1.captureWindowAfterMinutes > 0) ? P_1.captureWindowAfterMinutes.ToString() : "15") + "m after\n`" + (P_1.captureWindowStartUtc ?? "—") + "`\n`" + (P_1.captureWindowEndUtc ?? "—") + "`";
			}
			list.Add(new Dictionary<string, object>
			{
				["name"] = "Capture Window (UTC)",
				["value"] = value2,
				["inline"] = false
			});
			list.Add(new Dictionary<string, object>
			{
				["name"] = "Upload Progress",
				["value"] = $"Archives: **{P_3.archiveNames?.Count ?? 0}**\n" + $"Keys: **{P_3.uploadedKeys?.Count ?? 0}**\n" + "Data: **" + A(P_3.totalZipBytesUploaded) + "**",
				["inline"] = true
			});
			if (!string.IsNullOrWhiteSpace(P_2.a))
			{
				list.Add(new Dictionary<string, object>
				{
					["name"] = "Latest Upload Key",
					["value"] = "`" + P_2.a + "`",
					["inline"] = true
				});
			}
			if (!string.IsNullOrWhiteSpace(P_5))
			{
				list.Add(new Dictionary<string, object>
				{
					["name"] = "Archives",
					["value"] = A(P_5, 900),
					["inline"] = false
				});
			}
			if (!string.IsNullOrWhiteSpace(P_6))
			{
				list.Add(new Dictionary<string, object>
				{
					["name"] = "Combat Summary",
					["value"] = P_6,
					["inline"] = false
				});
			}
			return list;
		}

		private static string A(global::A.c P_0)
		{
			if (P_0 != null && P_0.NoiseReduction?.Enabled == true)
			{
				return "Quiet mode (noise reduction)";
			}
			if (P_0 != null && P_0.PerformanceMode?.Enabled == true)
			{
				return "Performance mode";
			}
			return null;
		}

		private string A(string P_0, int P_1)
		{
			if (string.IsNullOrEmpty(P_0))
			{
				return P_0;
			}
			if (P_0.Length <= P_1)
			{
				return P_0;
			}
			return P_0.Substring(0, Math.Max(0, P_1 - 3)) + "...";
		}

		private Dictionary<string, int> F(string P_0)
		{
			//IL_0028: Unknown result type (might be due to invalid IL or missing references)
			//IL_002e: Expected O, but got Unknown
			Dictionary<string, int> dictionary = new Dictionary<string, int>(StringComparer.OrdinalIgnoreCase);
			if (string.IsNullOrEmpty(P_0) || !File.Exists(P_0))
			{
				return dictionary;
			}
			try
			{
				using FileStream fileStream = File.OpenRead(P_0);
				ZipArchive val = new ZipArchive((Stream)fileStream, (ZipArchiveMode)0, false);
				try
				{
					foreach (ZipArchiveEntry entry in val.Entries)
					{
						if (entry == null)
						{
							continue;
						}
						string fullName = entry.FullName;
						if (string.IsNullOrEmpty(fullName) || !fullName.EndsWith(".events.json", StringComparison.OrdinalIgnoreCase))
						{
							continue;
						}
						using Stream stream = entry.Open();
						using StreamReader streamReader = new StreamReader(stream);
						string text = streamReader.ReadToEnd();
						if (string.IsNullOrWhiteSpace(text))
						{
							continue;
						}
						List<J> list;
						try
						{
							list = JsonConvert.DeserializeObject<List<J>>(text);
						}
						catch
						{
							list = null;
						}
						if (list == null)
						{
							continue;
						}
						foreach (J item in list)
						{
							if (item != null && !string.IsNullOrWhiteSpace(item.type))
							{
								if (!dictionary.TryGetValue(item.type, out var value))
								{
									dictionary[item.type] = 1;
								}
								else
								{
									dictionary[item.type] = value + 1;
								}
							}
						}
					}
				}
				finally
				{
					((IDisposable)val)?.Dispose();
				}
			}
			catch
			{
			}
			return dictionary;
		}

		private static string a(string P_0, int P_1 = 512)
		{
			if (string.IsNullOrEmpty(P_0))
			{
				return string.Empty;
			}
			P_0 = P_0.Replace("\r", " ").Replace("\n", " ").Trim();
			StringBuilder stringBuilder = new StringBuilder(P_0.Length);
			foreach (char c2 in P_0)
			{
				if (c2 >= ' ' && c2 <= '~')
				{
					stringBuilder.Append(c2);
				}
				else
				{
					stringBuilder.Append('?');
				}
			}
			string text = stringBuilder.ToString().Trim();
			if (text.Length > P_1)
			{
				text = text.Substring(0, P_1);
			}
			return text;
		}

		private static string B(string P_0, int P_1 = 1024)
		{
			if (string.IsNullOrEmpty(P_0))
			{
				return string.Empty;
			}
			string text = P_0.Replace("\r", " ").Replace("\n", " ").Trim();
			if (text.Length == 0)
			{
				return string.Empty;
			}
			byte[] array = O.m_A.GetBytes(text);
			if (array.Length > P_1)
			{
				Array.Resize(ref array, P_1);
				text = O.m_A.GetString(array);
				array = O.m_A.GetBytes(text);
			}
			return Convert.ToBase64String(array).TrimEnd('=').Replace('+', '-')
				.Replace('/', '_');
		}

		private static string f()
		{
			string identity = Server.identity;
			if (!string.IsNullOrWhiteSpace(identity))
			{
				return identity;
			}
			string hostname = Server.hostname;
			if (!string.IsNullOrWhiteSpace(hostname))
			{
				return hostname;
			}
			return "server";
		}

		private static int A(int P_0)
		{
			int num = Math.Max(1, P_0);
			return (int)Math.Min(60.0 * Math.Pow(2.0, num - 1), 3600.0);
		}

		private global::A.h f(string P_0)
		{
			//IL_001e: Unknown result type (might be due to invalid IL or missing references)
			//IL_0024: Expected O, but got Unknown
			try
			{
				if (string.IsNullOrEmpty(P_0) || !File.Exists(P_0))
				{
					return null;
				}
				using FileStream fileStream = File.OpenRead(P_0);
				ZipArchive val = new ZipArchive((Stream)fileStream, (ZipArchiveMode)0, false);
				try
				{
					ZipArchiveEntry entry = val.GetEntry("manifest.json");
					if (entry == null)
					{
						return null;
					}
					using Stream stream = entry.Open();
					using StreamReader streamReader = new StreamReader(stream);
					return JsonConvert.DeserializeObject<global::A.h>(streamReader.ReadToEnd());
				}
				finally
				{
					((IDisposable)val)?.Dispose();
				}
			}
			catch
			{
			}
			return null;
		}

		private static i G(string P_0)
		{
			try
			{
				if (!File.Exists(P_0))
				{
					return null;
				}
				return JsonConvert.DeserializeObject<i>(File.ReadAllText(P_0));
			}
			catch
			{
				return null;
			}
		}

		private static i g(string P_0)
		{
			DateTime dateTime = DateTime.UtcNow;
			DateTime dateTime2 = dateTime;
			try
			{
				dateTime = File.GetCreationTimeUtc(P_0);
			}
			catch
			{
			}
			try
			{
				dateTime2 = File.GetLastWriteTimeUtc(P_0);
			}
			catch
			{
			}
			return new i
			{
				demoPath = P_0,
				demoFileName = Path.GetFileName(P_0),
				startedUtc = dateTime.ToString("o"),
				endedUtc = dateTime2.ToString("o"),
				startedLocal = A(dateTime),
				endedLocal = A(dateTime2),
				chunkMinutes = 15,
				chunkReason = "upload-sweep"
			};
		}

		private static DateTime A(string P_0, DateTime P_1)
		{
			try
			{
				if (DateTime.TryParse(P_0, null, DateTimeStyles.RoundtripKind, out var result))
				{
					return result;
				}
			}
			catch
			{
			}
			return P_1;
		}

		private static DateTimeOffset G()
		{
			try
			{
				return DateTimeOffset.Now;
			}
			catch
			{
				return DateTimeOffset.UtcNow;
			}
		}

		private static double A(DateTimeOffset P_0)
		{
			try
			{
				return (double)P_0.ToUnixTimeMilliseconds() / 1000.0;
			}
			catch
			{
				return (double)P_0.ToUniversalTime().ToUnixTimeMilliseconds() / 1000.0;
			}
		}

		private static string a(DateTimeOffset P_0)
		{
			try
			{
				return P_0.ToString("o");
			}
			catch
			{
				return P_0.UtcDateTime.ToString("o");
			}
		}

		private static string A(DateTime P_0)
		{
			try
			{
				return a(new DateTimeOffset(P_0));
			}
			catch
			{
				return P_0.ToUniversalTime().ToString("o");
			}
		}

		private static string a(DateTime P_0)
		{
			try
			{
				if (P_0.Kind == DateTimeKind.Unspecified)
				{
					P_0 = DateTime.SpecifyKind(P_0, DateTimeKind.Utc);
				}
				return P_0.ToString("yyyyMMddTHHmmssZ");
			}
			catch
			{
				return DateTime.UtcNow.ToString("yyyyMMddTHHmmssZ");
			}
		}

		private static string H(string P_0)
		{
			if (string.IsNullOrEmpty(P_0))
			{
				return "unknown";
			}
			try
			{
				char[] invalidFileNameChars = Path.GetInvalidFileNameChars();
				StringBuilder stringBuilder = new StringBuilder();
				string text = P_0;
				foreach (char c2 in text)
				{
					if (invalidFileNameChars.Contains(c2) || c2 == ':' || c2 == ' ')
					{
						stringBuilder.Append('_');
					}
					else
					{
						stringBuilder.Append(c2);
					}
				}
				string text2 = stringBuilder.ToString().Trim('_');
				return string.IsNullOrEmpty(text2) ? "unknown" : text2;
			}
			catch
			{
				return "unknown";
			}
		}

		private static string A(long P_0)
		{
			double num = P_0;
			string[] array = new string[5] { "B", "KB", "MB", "GB", "TB" };
			int num2 = 0;
			while (num >= 1024.0 && num2 < array.Length - 1)
			{
				num /= 1024.0;
				num2++;
			}
			return $"{num:0.##}{array[num2]}";
		}

		private static int a(string P_0, string P_1)
		{
			try
			{
				int num = 1;
				foreach (string item in Directory.EnumerateFiles(P_0, P_1 + "*.zip", SearchOption.TopDirectoryOnly))
				{
					try
					{
						string[] array = Path.GetFileNameWithoutExtension(item).Split('_');
						if (array.Length != 0 && int.TryParse(array[array.Length - 1], out var result) && result >= num)
						{
							num = result + 1;
						}
					}
					catch
					{
					}
				}
				return num;
			}
			catch
			{
				return 1;
			}
		}

		private static string A(i P_0)
		{
			try
			{
				string text = H(P_0?.serverIdentity) ?? "server";
				string text2 = H(P_0?.steamId) ?? "player";
				DateTime.TryParse(P_0?.startedUtc, null, DateTimeStyles.RoundtripKind, out var result);
				string text3 = a(result);
				return text + ":" + text2 + ":" + text3;
			}
			catch
			{
				return "unknown";
			}
		}

		private static void B(string P_0, string P_1)
		{
			if (string.IsNullOrEmpty(P_0) || string.IsNullOrEmpty(P_1) || !File.Exists(P_0))
			{
				return;
			}
			try
			{
				string directoryName = Path.GetDirectoryName(P_1);
				if (!string.IsNullOrEmpty(directoryName))
				{
					Directory.CreateDirectory(directoryName);
				}
				if (File.Exists(P_1))
				{
					File.Delete(P_1);
				}
				File.Move(P_0, P_1);
				return;
			}
			catch
			{
			}
			try
			{
				File.Copy(P_0, P_1, overwrite: true);
				File.Delete(P_0);
			}
			catch
			{
			}
		}

		private static void h(string P_0)
		{
			try
			{
				if (File.Exists(P_0))
				{
					File.Delete(P_0);
				}
			}
			catch
			{
			}
		}

		private static bool I(string P_0)
		{
			try
			{
				if (File.Exists(P_0))
				{
					File.Delete(P_0);
				}
				return !File.Exists(P_0);
			}
			catch
			{
				return false;
			}
		}

		private static void i(string P_0)
		{
			try
			{
				if (!string.IsNullOrEmpty(P_0))
				{
					File.WriteAllText(P_0 + ".ack", "ack");
				}
			}
			catch
			{
			}
		}

		private void J(string P_0)
		{
			Diagnostics.LastSweepUtc = DateTime.UtcNow;
			if (string.IsNullOrEmpty(P_0) || !Directory.Exists(P_0))
			{
				Diagnostics.OutboxBytes = 0L;
				Diagnostics.ZipCount = 0;
				Diagnostics.AckCount = 0;
				return;
			}
			long num = 0L;
			int num2 = 0;
			int num3 = 0;
			try
			{
				foreach (string item in Directory.EnumerateFiles(P_0, "*.zip", SearchOption.AllDirectories))
				{
					num2++;
					try
					{
						num += new FileInfo(item).Length;
					}
					catch
					{
					}
				}
			}
			catch
			{
			}
			try
			{
				foreach (string item2 in Directory.EnumerateFiles(P_0, "*.zip.ack", SearchOption.AllDirectories))
				{
					_ = item2;
					num3++;
				}
			}
			catch
			{
			}
			Diagnostics.OutboxBytes = num;
			Diagnostics.ZipCount = num2;
			Diagnostics.AckCount = num3;
		}

		private static string j(string P_0)
		{
			if (string.IsNullOrEmpty(P_0))
			{
				return P_0;
			}
			if (!File.Exists(P_0))
			{
				return P_0;
			}
			string directoryName = Path.GetDirectoryName(P_0);
			string fileNameWithoutExtension = Path.GetFileNameWithoutExtension(P_0);
			string extension = Path.GetExtension(P_0);
			for (int l = 1; l <= 500; l++)
			{
				string text = Path.Combine(directoryName, $"{fileNameWithoutExtension}_{l}{extension}");
				if (!File.Exists(text))
				{
					return text;
				}
			}
			return P_0;
		}
	}
	internal sealed class C : SingletonComponent<C>
	{
		private const string m_A = "[RustDemoPro]";

		private const string m_a = "HarmonyMods_Data/RustDemoPro/Configuration.json";

		private static readonly string[] m_A = new string[2] { "carbon/configs/RustDemoPro.json", "oxide/config/RustDemoPro.json" };

		private const string m_B = "RustDemoPro";

		private const int m_A = 5;

		private const int m_a = 5;

		private readonly StringBuilder m_A = new StringBuilder(4096);

		private f m_A;

		private M m_A;

		private N m_A;

		private m m_A;

		private O m_A;

		private n m_A;

		private Coroutine m_A;

		private Coroutine m_a;

		private Coroutine m_B;

		private Coroutine m_b;

		private Coroutine m_C;

		private bool m_A;

		private bool m_a;

		private Command m_A;

		private Command m_a;

		private Command m_B;

		private bool m_B;

		private bool m_b;

		private int m_B;

		private Dictionary<string, bool> m_A;

		private bool m_C;

		[CompilerGenerated]
		private bool m_c;

		[CompilerGenerated]
		private c m_A = new c();

		public bool Ready
		{
			[CompilerGenerated]
			get
			{
				return this.m_c;
			}
			[CompilerGenerated]
			private set
			{
				this.m_c = value;
			}
		}

		public c Config
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			private set
			{
				this.m_A = value;
			}
		}

		public n.A StorageCleanupDiagnostics => this.m_A?.Diagnostics;

		public O.C OutboxDiagnostics => this.m_A?.Diagnostics;

		public HashSet<string> A()
		{
			return this.m_A?.a();
		}

		public static void a()
		{
			//IL_0013: Unknown result type (might be due to invalid IL or missing references)
			if (!((Object)(object)SingletonComponent<global::A.C>.Instance != (Object)null))
			{
				new GameObject("RustDemoPro").AddComponent<global::A.C>();
			}
		}

		public static void B()
		{
			if (!((Object)(object)SingletonComponent<global::A.C>.Instance == (Object)null))
			{
				try
				{
					SingletonComponent<global::A.C>.Instance.b();
				}
				catch
				{
				}
				Object.DestroyImmediate((Object)(object)SingletonComponent<global::A.C>.Instance);
			}
		}

		protected override void Awake()
		{
			((SingletonComponent)this).Awake();
			try
			{
				try
				{
					Object.DontDestroyOnLoad((Object)(object)((Component)this).gameObject);
				}
				catch
				{
				}
				h();
				i();
				this.m_A = new f("RustDemoPro");
				this.m_A.A();
				Ready = true;
				A("[RustDemoPro] BUILD MARKER: 0.2.1.0 + grouped-config-settings");
				A("[RustDemoPro] Runtime ready. Waiting for server start to register commands...");
			}
			catch (Exception ex)
			{
				Ready = false;
				Debug.LogError((object)"[RustDemoPro] Runtime failed to initialise.");
				Debug.LogException(ex);
			}
		}

		private void b()
		{
			d();
			try
			{
				this.m_A?.a();
			}
			catch
			{
			}
		}

		private void OnDestroy()
		{
			b();
		}

		public void C()
		{
			if (!this.m_B)
			{
				this.m_B = true;
				J();
				A("[RustDemoPro] Server start detected. Scheduling command registration...");
				f();
				c();
				A(ref this.m_A);
				this.m_A = ((MonoBehaviour)this).StartCoroutine(A(0.25f));
			}
		}

		private void c()
		{
			c config = Config;
			if (config == null || !config.Enabled)
			{
				d();
				return;
			}
			if (!this.m_C)
			{
				this.m_C = true;
				this.m_B = A(ref this.m_B, B(300f));
				this.m_a = A(ref this.m_a, a(1f));
			}
			D();
		}

		private void D()
		{
			if (this.m_C)
			{
				c config = Config;
				if (config != null && config.Upload?.Enabled == true)
				{
					this.m_b = A(ref this.m_b, b(300f));
					this.m_C = A(ref this.m_C, G());
					A($"[RustDemoPro] Upload routines started. Sweep={this.m_b != null} Tick={this.m_C != null}");
				}
				else
				{
					A(ref this.m_b);
					A(ref this.m_C);
					A("[RustDemoPro] Upload routines stopped (Upload.Enabled=false).");
				}
			}
		}

		private void d()
		{
			this.m_C = false;
			A(ref this.m_A);
			A(ref this.m_B);
			A(ref this.m_b);
			A(ref this.m_C);
			A(ref this.m_a);
		}

		private void A(ref Coroutine P_0)
		{
			if (P_0 != null)
			{
				try
				{
					((MonoBehaviour)this).StopCoroutine(P_0);
				}
				catch
				{
				}
				P_0 = null;
			}
		}

		private Coroutine A(ref Coroutine P_0, IEnumerator P_1)
		{
			A(ref P_0);
			try
			{
				P_0 = ((MonoBehaviour)this).StartCoroutine(P_1);
			}
			catch
			{
				P_0 = null;
			}
			return P_0;
		}

		private IEnumerator A(float P_0)
		{
			if (P_0 > 0f)
			{
				yield return (object)new WaitForSeconds(P_0);
			}
			g();
		}

		private IEnumerator a(float P_0)
		{
			WaitForSeconds val = new WaitForSeconds(P_0);
			while (this.m_C)
			{
				yield return val;
				E();
			}
		}

		private IEnumerator B(float P_0)
		{
			WaitForSeconds val = new WaitForSeconds(P_0);
			while (this.m_C)
			{
				yield return val;
				e();
			}
		}

		private IEnumerator b(float P_0)
		{
			WaitForSeconds val = new WaitForSeconds(P_0);
			while (this.m_C)
			{
				yield return val;
				F();
			}
		}

		private void E()
		{
			c config = Config;
			if (config == null || !config.Enabled)
			{
				return;
			}
			N obj = this.m_A;
			if (obj == null || !obj.Ready)
			{
				return;
			}
			try
			{
				this.m_A.A(DateTime.UtcNow);
			}
			catch
			{
			}
		}

		private void e()
		{
			c config = Config;
			if (config == null || !config.Enabled)
			{
				return;
			}
			n obj = this.m_A;
			if (obj == null || !obj.Ready)
			{
				return;
			}
			try
			{
				this.m_A.a();
			}
			catch
			{
			}
		}

		private void F()
		{
			c config = Config;
			if (config == null || !config.Enabled)
			{
				return;
			}
			c config2 = Config;
			if (config2 == null || config2.Upload?.Enabled != true)
			{
				return;
			}
			O o = this.m_A;
			if (o == null || !o.Ready)
			{
				return;
			}
			try
			{
				this.m_A.a();
			}
			catch
			{
			}
		}

		private void f()
		{
			N obj = this.m_A;
			if (obj == null || !obj.Ready)
			{
				return;
			}
			try
			{
				ListHashSet<BasePlayer> activePlayerList = BasePlayer.activePlayerList;
				if (activePlayerList == null || activePlayerList.Count == 0)
				{
					return;
				}
				for (int l = 0; l < activePlayerList.Count; l++)
				{
					BasePlayer val = activePlayerList[l];
					if (!((Object)(object)val == (Object)null))
					{
						this.m_A.b(val);
					}
				}
			}
			catch
			{
			}
		}

		private IEnumerator G()
		{
			WaitForSeconds val = new WaitForSeconds(5f);
			while (this.m_C)
			{
				try
				{
					if (this.m_A?.Ready ?? false)
					{
						A("[RustDemoPro] Upload tick loop running.");
						this.m_A.B();
					}
				}
				catch
				{
				}
				yield return val;
			}
		}

		private void g()
		{
			if (this.m_b)
			{
				return;
			}
			this.m_B++;
			if (this.m_B > 5)
			{
				Debug.LogWarning((object)string.Format("{0} Command registration failed after {1} attempts. Giving up.", "[RustDemoPro]", this.m_B - 1));
				return;
			}
			H();
			bool flag = A("rdm.status", this.m_A) & A("rdm.reloadcfg", this.m_a) & A("rdm.patchhealth", this.m_B);
			bool flag2 = A(this.m_a, this.m_A, this.m_B);
			if (flag && flag2)
			{
				this.m_b = true;
				A("[RustDemoPro] Commands active: rdm.status, rdm.reloadcfg, rdm.patchhealth");
				return;
			}
			Debug.LogWarning((object)string.Format("{0} Command registration attempt #{1} failed (dictOk={2}, allOk={3}). Retrying...", "[RustDemoPro]", this.m_B, flag, flag2));
			A(ref this.m_A);
			this.m_A = ((MonoBehaviour)this).StartCoroutine(A(1f));
		}

		private void H()
		{
			//IL_0009: Unknown result type (might be due to invalid IL or missing references)
			//IL_000e: Unknown result type (might be due to invalid IL or missing references)
			//IL_0019: Unknown result type (might be due to invalid IL or missing references)
			//IL_0024: Unknown result type (might be due to invalid IL or missing references)
			//IL_002f: Unknown result type (might be due to invalid IL or missing references)
			//IL_0036: Unknown result type (might be due to invalid IL or missing references)
			//IL_003d: Unknown result type (might be due to invalid IL or missing references)
			//IL_0054: Expected O, but got Unknown
			//IL_005d: Unknown result type (might be due to invalid IL or missing references)
			//IL_0062: Unknown result type (might be due to invalid IL or missing references)
			//IL_006d: Unknown result type (might be due to invalid IL or missing references)
			//IL_0078: Unknown result type (might be due to invalid IL or missing references)
			//IL_0083: Unknown result type (might be due to invalid IL or missing references)
			//IL_008a: Unknown result type (might be due to invalid IL or missing references)
			//IL_0091: Unknown result type (might be due to invalid IL or missing references)
			//IL_00a8: Expected O, but got Unknown
			//IL_00b1: Unknown result type (might be due to invalid IL or missing references)
			//IL_00b6: Unknown result type (might be due to invalid IL or missing references)
			//IL_00c1: Unknown result type (might be due to invalid IL or missing references)
			//IL_00cc: Unknown result type (might be due to invalid IL or missing references)
			//IL_00d7: Unknown result type (might be due to invalid IL or missing references)
			//IL_00de: Unknown result type (might be due to invalid IL or missing references)
			//IL_00e5: Unknown result type (might be due to invalid IL or missing references)
			//IL_00fc: Expected O, but got Unknown
			if (this.m_a == null)
			{
				this.m_a = new Command
				{
					Name = "reloadcfg",
					Parent = "rdm",
					FullName = "rdm.reloadcfg",
					ServerAdmin = true,
					Variable = false,
					Call = B
				};
			}
			if (this.m_A == null)
			{
				this.m_A = new Command
				{
					Name = "status",
					Parent = "rdm",
					FullName = "rdm.status",
					ServerAdmin = true,
					Variable = false,
					Call = A
				};
			}
			if (this.m_B == null)
			{
				this.m_B = new Command
				{
					Name = "patchhealth",
					Parent = "rdm",
					FullName = "rdm.patchhealth",
					ServerAdmin = true,
					Variable = false,
					Call = a
				};
			}
		}

		private bool A(string P_0, Command P_1)
		{
			try
			{
				Server.Dict[P_0] = P_1;
				return true;
			}
			catch (Exception ex)
			{
				Debug.LogWarning((object)("[RustDemoPro] Failed to register '" + P_0 + "' into Index.Server.Dict: " + ex.Message));
				return false;
			}
		}

		private bool A(params Command[] commandsToAdd)
		{
			try
			{
				Command[] array = Index.All;
				if (array == null)
				{
					array = Array.Empty<Command>();
				}
				Command[] array2 = array;
				foreach (Command val in commandsToAdd)
				{
					if (val == null)
					{
						continue;
					}
					bool flag = false;
					for (int num = 0; num < array2.Length; num++)
					{
						if (array2[num] == val)
						{
							flag = true;
							break;
						}
					}
					if (!flag)
					{
						array2 = array2.Concat((IEnumerable<Command>)(object)new Command[1] { val }).ToArray();
					}
				}
				Type typeFromHandle = typeof(Index);
				PropertyInfo property = typeFromHandle.GetProperty("All", BindingFlags.Static | BindingFlags.Public);
				if (property != null && property.CanWrite)
				{
					property.SetValue(null, array2, null);
					return true;
				}
				FieldInfo[] fields = typeFromHandle.GetFields(BindingFlags.Static | BindingFlags.Public | BindingFlags.NonPublic);
				FieldInfo fieldInfo = typeFromHandle.GetField("<All>k__BackingField", BindingFlags.Static | BindingFlags.NonPublic);
				if (fieldInfo != null && fieldInfo.FieldType == typeof(Command[]))
				{
					fieldInfo.SetValue(null, array2);
					return true;
				}
				foreach (FieldInfo fieldInfo2 in fields)
				{
					if (!(fieldInfo2.FieldType != typeof(Command[])) && fieldInfo2.GetValue(null) as Command[] == array)
					{
						fieldInfo = fieldInfo2;
						break;
					}
				}
				if (fieldInfo == null)
				{
					foreach (FieldInfo fieldInfo3 in fields)
					{
						if (!(fieldInfo3.FieldType != typeof(Command[])) && fieldInfo3.Name.ToLowerInvariant().Contains("all"))
						{
							fieldInfo = fieldInfo3;
							break;
						}
					}
				}
				if (fieldInfo == null)
				{
					Debug.LogWarning((object)"[RustDemoPro] Could not find backing field for ConsoleSystem.Index.All");
					return false;
				}
				fieldInfo.SetValue(null, array2);
				return true;
			}
			catch (Exception ex)
			{
				Debug.LogWarning((object)("[RustDemoPro] Failed to replace Index.All (RSM-style): " + ex.Message));
				return false;
			}
		}

		private void A(Arg P_0, string P_1)
		{
			bool flag = false;
			try
			{
				if (P_0 != null)
				{
					P_0.ReplyWith(P_1);
					flag = true;
				}
			}
			catch
			{
			}
			if (!flag)
			{
				try
				{
					Debug.Log((object)P_1);
				}
				catch
				{
				}
			}
		}

		private void A(Arg P_0)
		{
			this.m_A.Clear();
			string value = Assembly.GetExecutingAssembly().GetName().Version?.ToString() ?? "unknown";
			this.m_A.AppendLine("[RustDemoPro] Status");
			this.m_A.AppendLine("Overview");
			this.m_A.Append("\tReady: ").Append(Ready).AppendLine();
			this.m_A.Append("\tVersion: ").Append(value).AppendLine();
			this.m_A.Append("\tConfigPath: ").Append("HarmonyMods_Data/RustDemoPro/Configuration.json").AppendLine();
			this.m_A.Append("\tHarmonyId: ").Append("RustDemoPro").AppendLine();
			this.m_A.AppendLine("Config");
			if (Config == null)
			{
				this.m_A.AppendLine("\t<null>");
			}
			else
			{
				this.m_A.Append("\tConfigVersion: ").Append(Config.ConfigVersion).AppendLine();
				this.m_A.Append("\tEnabled: ").Append(Config.Enabled).AppendLine();
				this.m_A.Append("\tUploadEnabled: ").Append(Config.Upload?.Enabled ?? false).AppendLine();
				this.m_A.Append("\tStorageLimitGB: ").Append(Config.StorageLimitGB).AppendLine();
				this.m_A.Append("\tOutboxMaxGB: ").Append(Config.Upload?.OutboxMaxGB ?? 0).AppendLine();
			}
			this.m_A.AppendLine("Runtime");
			this.m_A.Append("\tServerStarted: ").Append(this.m_B).AppendLine();
			this.m_A.Append("\tCommandsRegistered: ").Append(this.m_b).AppendLine();
			long num = 0L;
			long num2 = 0L;
			string value2 = string.Empty;
			if (this.m_A != null)
			{
				try
				{
					num = this.m_A.B();
				}
				catch
				{
				}
				try
				{
					num2 = this.m_A.C();
				}
				catch
				{
				}
				try
				{
					value2 = this.m_A.b();
				}
				catch
				{
				}
			}
			this.m_A.AppendLine("Storage");
			this.m_A.Append("\tUsage: ").Append(A(num)).Append(" / ")
				.Append(A(num2))
				.Append(" (")
				.Append(Config?.StorageLimitGB ?? 0.0)
				.Append(" GB)")
				.AppendLine();
			if (!string.IsNullOrEmpty(value2))
			{
				this.m_A.Append("\tDemosRoot: ").Append(value2).AppendLine();
			}
			n.A a2 = this.m_A?.Diagnostics;
			this.m_A.AppendLine("Cleanup");
			if (a2 == null)
			{
				this.m_A.AppendLine("\t<unavailable>");
			}
			else
			{
				this.m_A.Append("\tLastRunUtc: ").Append(A(a2.LastRunUtc)).AppendLine();
				this.m_A.Append("\tDeletedFiles: ").Append(a2.DeletedFiles).AppendLine();
				this.m_A.Append("\tDeletedFolders: ").Append(a2.DeletedFolders).AppendLine();
				this.m_A.Append("\tDeletedBytes: ").Append(A(a2.DeletedBytes)).AppendLine();
			}
			O.C c2 = this.m_A?.Diagnostics;
			this.m_A.AppendLine("Outbox");
			if (c2 == null)
			{
				this.m_A.AppendLine("\t<unavailable>");
			}
			else
			{
				this.m_A.Append("\tLastSweepUtc: ").Append(A(c2.LastSweepUtc)).AppendLine();
				this.m_A.Append("\tOutboxSize: ").Append(A(c2.OutboxBytes)).AppendLine();
				this.m_A.Append("\tZipCount: ").Append(c2.ZipCount).AppendLine();
				this.m_A.Append("\tAckCount: ").Append(c2.AckCount).AppendLine();
			}
			this.m_A.AppendLine("Services");
			A("RecordingService", this.m_A?.Initialized, this.m_A?.Ready);
			A("CombatEventService", this.m_A?.Initialized, this.m_A?.Ready);
			A("IncidentService", this.m_A?.Initialized, this.m_A?.Ready);
			A("UploadService", this.m_A?.Initialized, this.m_A?.Ready);
			A("StorageCleanupService", this.m_A?.Initialized, this.m_A?.Ready);
			A(P_0, this.m_A.ToString());
		}

		private void a(Arg P_0)
		{
			if (this.m_A == null)
			{
				A(P_0, "[RustDemoPro] Patch Health\n\tPatchManager not initialised.");
			}
			else
			{
				A(P_0, this.m_A.A("[RustDemoPro]"));
			}
		}

		private void B(Arg P_0)
		{
			try
			{
				c config = Config;
				bool flag = config != null && config.Upload?.Enabled == true;
				h();
				c();
				if (this.m_B)
				{
					c config2 = Config;
					if (config2 != null && config2.Enabled)
					{
						c config3 = Config;
						if (config3 != null && config3.Upload?.Enabled == true && !flag)
						{
							try
							{
								this.m_A?.a();
							}
							catch
							{
							}
							try
							{
								this.m_A?.B();
							}
							catch
							{
							}
						}
					}
				}
				A(P_0, "[RustDemoPro] Configuration reloaded.");
			}
			catch (Exception ex)
			{
				Debug.LogError((object)"[RustDemoPro] Failed to reload configuration.");
				Debug.LogException(ex);
				A(P_0, "[RustDemoPro] Failed to reload configuration. Check server console.");
			}
		}

		private void h()
		{
			c c2 = null;
			if (!File.Exists("HarmonyMods_Data/RustDemoPro/Configuration.json"))
			{
				c2 = A(out var text);
				if (c2 != null)
				{
					Debug.LogWarning((object)("[RustDemoPro] Config missing. Migrating legacy config from " + text + " to HarmonyMods_Data/RustDemoPro/Configuration.json"));
				}
				else
				{
					Debug.LogWarning((object)"[RustDemoPro] Config missing. Writing defaults: HarmonyMods_Data/RustDemoPro/Configuration.json");
				}
			}
			else
			{
				try
				{
					c2 = JsonConvert.DeserializeObject<c>(File.ReadAllText("HarmonyMods_Data/RustDemoPro/Configuration.json"));
					if (c2 == null)
					{
						throw new InvalidDataException("Config deserialized to null.");
					}
				}
				catch
				{
					Debug.LogError((object)"[RustDemoPro] Config missing or malformed. Defaults will be loaded.");
				}
			}
			Config = c2 ?? new c();
			A(Config);
			j();
			I();
		}

		private static c A(out string P_0)
		{
			P_0 = null;
			string[] array = global::A.C.m_A;
			foreach (string text in array)
			{
				if (File.Exists(text))
				{
					try
					{
						c result = JsonConvert.DeserializeObject<c>(File.ReadAllText(text)) ?? throw new InvalidDataException("Legacy config deserialized to null.");
						P_0 = text;
						return result;
					}
					catch (Exception ex)
					{
						Debug.LogWarning((object)("[RustDemoPro] Legacy config at " + text + " failed to load: " + ex.Message));
					}
				}
			}
			return null;
		}

		private void I()
		{
			try
			{
				A(Config);
				j();
				FileInfo fileInfo = new FileInfo("HarmonyMods_Data/RustDemoPro/Configuration.json");
				if (fileInfo.Directory != null && !fileInfo.Directory.Exists)
				{
					fileInfo.Directory.Create();
				}
				string contents = JsonConvert.SerializeObject((object)Config, (Formatting)1);
				File.WriteAllText("HarmonyMods_Data/RustDemoPro/Configuration.json", contents);
			}
			catch (Exception ex)
			{
				Debug.LogError((object)"[RustDemoPro] Failed to write configuration file.");
				Debug.LogException(ex);
			}
		}

		private void i()
		{
			if (!this.m_A)
			{
				this.m_A = true;
				global::A.a a2 = new global::A.a();
				M m2 = (this.m_A = new M());
				N n2 = (this.m_A = new N(m2, a2));
				m m3 = new m(n2, m2, this.m_A = new O(n2, a2), (MonoBehaviour)(object)this);
				this.m_A = m3;
				n n3 = new n(n2, a2);
				this.m_A = n3;
				K();
			}
		}

		private void J()
		{
			if (!this.m_a)
			{
				this.m_a = true;
				A((global::A.B)this.m_A, "CombatEventService");
				A((global::A.B)this.m_A, "RecordingService");
				A((global::A.B)this.m_A, "IncidentService");
				A((global::A.B)this.m_A, "UploadService");
				A((global::A.B)this.m_A, "StorageCleanupService");
			}
		}

		private void A(global::A.B P_0, string P_1)
		{
			try
			{
				if (P_0 == null)
				{
					Debug.LogWarning((object)("[RustDemoPro] " + P_1 + " missing; cannot initialize."));
				}
				else
				{
					P_0.A();
				}
			}
			catch (Exception ex)
			{
				Debug.LogWarning((object)("[RustDemoPro] " + P_1 + " failed to initialize: " + ex.Message));
			}
		}

		private void A(string P_0, bool? P_1, bool? P_2)
		{
			this.m_A.Append("\t").Append(P_0).Append(": ");
			this.m_A.Append("Initialized=").Append(P_1 == true);
			this.m_A.Append(", Ready=").Append(P_2 == true);
			this.m_A.AppendLine();
		}

		private static void A(c P_0)
		{
			if (P_0 == null)
			{
				return;
			}
			int configVersion = P_0.ConfigVersion;
			P_0.A();
			if (P_0.ConfigVersion <= 0)
			{
				P_0.ConfigVersion = 1;
			}
			if (P_0.ConfigVersion < 1)
			{
				P_0.ConfigVersion = 1;
			}
			if (P_0.Logging == null)
			{
				P_0.Logging = new D();
			}
			if (P_0.NoiseReduction == null)
			{
				P_0.NoiseReduction = new d();
			}
			if (P_0.PerformanceMode == null)
			{
				P_0.PerformanceMode = new E();
			}
			if (P_0.Upload == null)
			{
				P_0.Upload = new e();
			}
			if (P_0.Upload.IncidentWebhook == null)
			{
				P_0.Upload.IncidentWebhook = new F();
			}
			if (P_0.Upload.OutboxMaxGB < 1)
			{
				P_0.Upload.OutboxMaxGB = 1;
			}
			if (P_0.ReportCaptureTypes == null || P_0.ReportCaptureTypes.Count == 0)
			{
				P_0.ReportCaptureTypes = new Dictionary<string, bool>(StringComparer.OrdinalIgnoreCase)
				{
					["name"] = true,
					["cheat"] = true,
					["break_server_rules"] = true,
					["abusive"] = true,
					["spam"] = true
				};
			}
			if (configVersion < 9)
			{
				if (P_0.ReportThresholdEnabled)
				{
					P_0.NoiseReduction.Enabled = true;
				}
				if (P_0.ReportThresholdCount > 0)
				{
					P_0.NoiseReduction.ReportThresholdCount = P_0.ReportThresholdCount;
				}
				if (P_0.ReportThresholdWindowMinutes > 0)
				{
					P_0.NoiseReduction.ReportThresholdWindowMinutes = P_0.ReportThresholdWindowMinutes;
				}
			}
			if (P_0.NoiseReduction.ReportThresholdCount < 1)
			{
				P_0.NoiseReduction.ReportThresholdCount = 1;
			}
			if (P_0.NoiseReduction.ReportThresholdWindowMinutes < 1)
			{
				P_0.NoiseReduction.ReportThresholdWindowMinutes = 1;
			}
			if (P_0.PerformanceMode.ReportThresholdCount < 1)
			{
				P_0.PerformanceMode.ReportThresholdCount = 1;
			}
			if (P_0.PerformanceMode.ReportThresholdWindowMinutes < 1)
			{
				P_0.PerformanceMode.ReportThresholdWindowMinutes = 1;
			}
			if (P_0.PerformanceMode.RecordHours < 1)
			{
				P_0.PerformanceMode.RecordHours = 1;
			}
			if (P_0.PerformanceMode.Enabled && P_0.NoiseReduction.Enabled)
			{
				P_0.PerformanceMode.Enabled = false;
			}
		}

		private void j()
		{
			this.m_A = global::A.b.A(Config?.ReportCaptureTypes);
			K();
		}

		private void K()
		{
			if (this.m_A != null)
			{
				this.m_A.A(Config?.Enabled ?? true, this.m_A, (Config?.NoiseReduction?.Enabled).GetValueOrDefault(), Config?.NoiseReduction?.ReportThresholdCount ?? 1, (Config?.NoiseReduction?.ReportThresholdWindowMinutes).GetValueOrDefault(), (Config?.PerformanceMode?.Enabled).GetValueOrDefault(), Config?.PerformanceMode?.ReportThresholdCount ?? 1, (Config?.PerformanceMode?.ReportThresholdWindowMinutes).GetValueOrDefault());
				this.m_A?.A((Config?.PerformanceMode?.Enabled).GetValueOrDefault(), (Config?.PerformanceMode?.RecordHours).GetValueOrDefault());
			}
		}

		public void A(BasePlayer P_0)
		{
			c config = Config;
			if (config != null && config.Logging?.Starts == true)
			{
				Debug.Log((object)("[RustDemoPro] PlayerInit seen: " + b(P_0) + " (" + C(P_0) + ")"));
			}
			N obj = this.m_A;
			if (obj != null && obj.Ready)
			{
				try
				{
					this.m_A.A(P_0);
				}
				catch
				{
				}
			}
		}

		public void a(BasePlayer P_0)
		{
			c config = Config;
			if (config != null && config.Logging?.Starts == true)
			{
				Debug.Log((object)("[RustDemoPro] PlayerConnected seen: " + b(P_0) + " (" + C(P_0) + ")"));
			}
			N obj = this.m_A;
			if (obj != null && obj.Ready)
			{
				try
				{
					this.m_A.a(P_0);
				}
				catch
				{
				}
			}
		}

		public void B(BasePlayer P_0)
		{
			c config = Config;
			if (config != null && config.Logging?.Stops == true)
			{
				Debug.Log((object)("[RustDemoPro] PlayerDisconnected seen: " + b(P_0) + " (" + C(P_0) + ")"));
			}
			N obj = this.m_A;
			if (obj != null && obj.Ready)
			{
				try
				{
					this.m_A.B(P_0);
				}
				catch
				{
				}
			}
		}

		public void A(BaseCombatEntity P_0, HitInfo P_1)
		{
			N obj = this.m_A;
			if (obj != null && obj.Ready)
			{
				try
				{
					this.m_A.A(P_0, P_1);
				}
				catch
				{
				}
			}
		}

		public void a(BaseCombatEntity P_0, HitInfo P_1)
		{
			N obj = this.m_A;
			if (obj != null && obj.Ready)
			{
				try
				{
					this.m_A.a(P_0, P_1);
				}
				catch
				{
				}
			}
		}

		public void A(BasePlayer P_0, RPCMessage P_1)
		{
		}

		public void A(string P_0, string P_1, string P_2, string P_3, string P_4, string P_5, string P_6)
		{
			A("[RustDemoPro] SeenDetailed: reporter=" + P_0 + " target=" + P_2 + " type=" + P_4 + " subject=" + P_5);
			m obj = this.m_A;
			if (obj != null && obj.Ready)
			{
				try
				{
					this.m_A.A(P_0, P_1, P_2, P_3, P_4, P_5, P_6);
				}
				catch
				{
				}
			}
		}

		private void A(string P_0)
		{
			c config = Config;
			if (config == null || config.Logging?.Debug != true)
			{
				return;
			}
			try
			{
				Debug.Log((object)P_0);
			}
			catch
			{
			}
		}

		private static string b(BasePlayer P_0)
		{
			try
			{
				return ((P_0 != null) ? P_0.displayName : null) ?? "";
			}
			catch
			{
				return "";
			}
		}

		private static string C(BasePlayer P_0)
		{
			try
			{
				return P_0?.UserIDString ?? "";
			}
			catch
			{
				return "";
			}
		}

		private static string A(long P_0)
		{
			double num = P_0;
			string[] array = new string[5] { "B", "KB", "MB", "GB", "TB" };
			int num2 = 0;
			while (num >= 1024.0 && num2 < array.Length - 1)
			{
				num /= 1024.0;
				num2++;
			}
			return $"{num:0.##}{array[num2]}";
		}

		private static string A(DateTime? P_0)
		{
			if (!P_0.HasValue || P_0.Value == DateTime.MinValue)
			{
				return "n/a";
			}
			try
			{
				return P_0.Value.ToString("o");
			}
			catch
			{
				return "n/a";
			}
		}
	}
	internal sealed class c
	{
		[CompilerGenerated]
		private int m_A = 1;

		[CompilerGenerated]
		private bool m_A = true;

		[CompilerGenerated]
		private double m_A = 5.0;

		[CompilerGenerated]
		private D m_A = new D();

		[CompilerGenerated]
		private Dictionary<string, bool> m_A = new Dictionary<string, bool>(StringComparer.OrdinalIgnoreCase)
		{
			["name"] = true,
			["cheat"] = true,
			["break_server_rules"] = true,
			["abusive"] = true,
			["spam"] = true
		};

		[CompilerGenerated]
		private d m_A = new d();

		[CompilerGenerated]
		private bool a;

		[CompilerGenerated]
		private int a;

		[CompilerGenerated]
		private int B;

		[CompilerGenerated]
		private E m_A = new E();

		[CompilerGenerated]
		private e m_A = new e();

		[CompilerGenerated]
		private IDictionary<string, JToken> m_A = new Dictionary<string, JToken>(StringComparer.OrdinalIgnoreCase);

		public int ConfigVersion
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			set
			{
				this.m_A = value;
			}
		}

		public bool Enabled
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			set
			{
				this.m_A = value;
			}
		}

		public double StorageLimitGB
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			set
			{
				this.m_A = value;
			}
		}

		public D Logging
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			set
			{
				this.m_A = value;
			}
		}

		public Dictionary<string, bool> ReportCaptureTypes
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			set
			{
				this.m_A = value;
			}
		}

		public d NoiseReduction
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			set
			{
				this.m_A = value;
			}
		}

		[JsonIgnore]
		public bool ReportThresholdEnabled
		{
			[CompilerGenerated]
			get
			{
				return this.a;
			}
			[CompilerGenerated]
			set
			{
				this.a = value;
			}
		}

		[JsonIgnore]
		public int ReportThresholdCount
		{
			[CompilerGenerated]
			get
			{
				return a;
			}
			[CompilerGenerated]
			set
			{
				a = value;
			}
		}

		[JsonIgnore]
		public int ReportThresholdWindowMinutes
		{
			[CompilerGenerated]
			get
			{
				return B;
			}
			[CompilerGenerated]
			set
			{
				B = value;
			}
		}

		public E PerformanceMode
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			set
			{
				this.m_A = value;
			}
		}

		public e Upload
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			set
			{
				this.m_A = value;
			}
		}

		[JsonExtensionData]
		public IDictionary<string, JToken> LegacyFields
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
			[CompilerGenerated]
			set
			{
				this.m_A = value;
			}
		}

		public void A()
		{
			if (LegacyFields != null && LegacyFields.Count != 0)
			{
				if (Logging == null)
				{
					Logging = new D();
				}
				if (NoiseReduction == null)
				{
					NoiseReduction = new d();
				}
				if (PerformanceMode == null)
				{
					PerformanceMode = new E();
				}
				if (Upload == null)
				{
					Upload = new e();
				}
				if (Upload.IncidentWebhook == null)
				{
					Upload.IncidentWebhook = new F();
				}
				if (A("LogStarts", out bool starts))
				{
					Logging.Starts = starts;
				}
				if (A("LogStops", out bool stops))
				{
					Logging.Stops = stops;
				}
				if (A("LogRotations", out bool rotations))
				{
					Logging.Rotations = rotations;
				}
				if (A("LogCleanup", out bool cleanup))
				{
					Logging.Cleanup = cleanup;
				}
				if (A("LogDebug", out bool debug))
				{
					Logging.Debug = debug;
				}
				LegacyFields.Remove("LogStarts");
				LegacyFields.Remove("LogStops");
				LegacyFields.Remove("LogRotations");
				LegacyFields.Remove("LogCleanup");
				LegacyFields.Remove("LogDebug");
				if (A("NoiseReductionEnabled", out bool enabled))
				{
					NoiseReduction.Enabled = enabled;
				}
				if (A("NoiseReductionReportThresholdCount", out int reportThresholdCount))
				{
					NoiseReduction.ReportThresholdCount = reportThresholdCount;
				}
				if (A("NoiseReductionReportThresholdWindowMinutes", out int reportThresholdWindowMinutes))
				{
					NoiseReduction.ReportThresholdWindowMinutes = reportThresholdWindowMinutes;
				}
				LegacyFields.Remove("NoiseReductionEnabled");
				LegacyFields.Remove("NoiseReductionReportThresholdCount");
				LegacyFields.Remove("NoiseReductionReportThresholdWindowMinutes");
				if (A("PerformanceModeEnabled", out bool enabled2))
				{
					PerformanceMode.Enabled = enabled2;
				}
				if (A("PerformanceModeReportThresholdCount", out int reportThresholdCount2))
				{
					PerformanceMode.ReportThresholdCount = reportThresholdCount2;
				}
				if (A("PerformanceModeReportThresholdWindowMinutes", out int reportThresholdWindowMinutes2))
				{
					PerformanceMode.ReportThresholdWindowMinutes = reportThresholdWindowMinutes2;
				}
				if (A("PerformanceModeRecordHours", out int recordHours))
				{
					PerformanceMode.RecordHours = recordHours;
				}
				LegacyFields.Remove("PerformanceModeEnabled");
				LegacyFields.Remove("PerformanceModeReportThresholdCount");
				LegacyFields.Remove("PerformanceModeReportThresholdWindowMinutes");
				LegacyFields.Remove("PerformanceModeRecordHours");
				if (A("UploadEnabled", out bool enabled3))
				{
					Upload.Enabled = enabled3;
				}
				if (A("OutboxMaxGB", out int outboxMaxGB))
				{
					Upload.OutboxMaxGB = outboxMaxGB;
				}
				if (A("UploadUrl", out string url))
				{
					Upload.Url = url;
				}
				if (A("UploadApiKey", out string apiKey))
				{
					Upload.ApiKey = apiKey;
				}
				if (A("PortalBaseUrl", out string portalBaseUrl))
				{
					Upload.PortalBaseUrl = portalBaseUrl;
				}
				if (A("IncidentWebhookUrl", out string url2))
				{
					Upload.IncidentWebhook.Url = url2;
				}
				if (A("IncidentWebhookColor", out int color))
				{
					Upload.IncidentWebhook.Color = color;
				}
				if (A("IncidentWebhookUsername", out string username))
				{
					Upload.IncidentWebhook.Username = username;
				}
				if (A("IncidentWebhookAvatarUrl", out string avatarUrl))
				{
					Upload.IncidentWebhook.AvatarUrl = avatarUrl;
				}
				if (A("IncidentWebhookIncludeEventSummary", out bool includeEventSummary))
				{
					Upload.IncidentWebhook.IncludeEventSummary = includeEventSummary;
				}
				LegacyFields.Remove("UploadEnabled");
				LegacyFields.Remove("OutboxMaxGB");
				LegacyFields.Remove("UploadUrl");
				LegacyFields.Remove("UploadApiKey");
				LegacyFields.Remove("PortalBaseUrl");
				LegacyFields.Remove("IncidentWebhookUrl");
				LegacyFields.Remove("IncidentWebhookColor");
				LegacyFields.Remove("IncidentWebhookUsername");
				LegacyFields.Remove("IncidentWebhookAvatarUrl");
				LegacyFields.Remove("IncidentWebhookIncludeEventSummary");
				if (A("ReportThresholdEnabled", out bool reportThresholdEnabled))
				{
					ReportThresholdEnabled = reportThresholdEnabled;
				}
				if (A("ReportThresholdCount", out int reportThresholdCount3))
				{
					ReportThresholdCount = reportThresholdCount3;
				}
				if (A("ReportThresholdWindowMinutes", out int reportThresholdWindowMinutes3))
				{
					ReportThresholdWindowMinutes = reportThresholdWindowMinutes3;
				}
				LegacyFields.Remove("ReportThresholdEnabled");
				LegacyFields.Remove("ReportThresholdCount");
				LegacyFields.Remove("ReportThresholdWindowMinutes");
				if (LegacyFields.Count == 0)
				{
					LegacyFields = null;
				}
			}
		}

		private bool A(string P_0, out bool P_1)
		{
			//IL_0023: Unknown result type (might be due to invalid IL or missing references)
			//IL_002a: Invalid comparison between Unknown and I4
			P_1 = false;
			if (LegacyFields == null || !LegacyFields.TryGetValue(P_0, out var value) || value == null)
			{
				return false;
			}
			try
			{
				P_1 = (((int)value.Type == 9) ? Extensions.Value<bool>((IEnumerable<JToken>)value) : bool.Parse(((object)value).ToString()));
				return true;
			}
			catch
			{
				return false;
			}
		}

		private bool A(string P_0, out int P_1)
		{
			//IL_0023: Unknown result type (might be due to invalid IL or missing references)
			//IL_0029: Invalid comparison between Unknown and I4
			P_1 = 0;
			if (LegacyFields == null || !LegacyFields.TryGetValue(P_0, out var value) || value == null)
			{
				return false;
			}
			try
			{
				P_1 = (((int)value.Type == 6) ? Extensions.Value<int>((IEnumerable<JToken>)value) : int.Parse(((object)value).ToString()));
				return true;
			}
			catch
			{
				return false;
			}
		}

		private bool A(string P_0, out string P_1)
		{
			//IL_0023: Unknown result type (might be due to invalid IL or missing references)
			//IL_0029: Invalid comparison between Unknown and I4
			P_1 = null;
			if (LegacyFields == null || !LegacyFields.TryGetValue(P_0, out var value) || value == null)
			{
				return false;
			}
			try
			{
				P_1 = (((int)value.Type == 8) ? Extensions.Value<string>((IEnumerable<JToken>)value) : ((object)value).ToString());
				return true;
			}
			catch
			{
				return false;
			}
		}
	}
	internal sealed class D
	{
		[CompilerGenerated]
		private bool A;

		[CompilerGenerated]
		private bool a;

		[CompilerGenerated]
		private bool B;

		[CompilerGenerated]
		private bool b;

		[CompilerGenerated]
		private bool C;

		public bool Starts
		{
			[CompilerGenerated]
			get
			{
				return A;
			}
			[CompilerGenerated]
			set
			{
				A = value;
			}
		}

		public bool Stops
		{
			[CompilerGenerated]
			get
			{
				return a;
			}
			[CompilerGenerated]
			set
			{
				a = value;
			}
		}

		public bool Rotations
		{
			[CompilerGenerated]
			get
			{
				return B;
			}
			[CompilerGenerated]
			set
			{
				B = value;
			}
		}

		public bool Cleanup
		{
			[CompilerGenerated]
			get
			{
				return b;
			}
			[CompilerGenerated]
			set
			{
				b = value;
			}
		}

		public bool Debug
		{
			[CompilerGenerated]
			get
			{
				return C;
			}
			[CompilerGenerated]
			set
			{
				C = value;
			}
		}
	}
	internal sealed class d
	{
		[CompilerGenerated]
		private bool A;

		[CompilerGenerated]
		private int A = 3;

		[CompilerGenerated]
		private int a = 1440;

		public bool Enabled
		{
			[CompilerGenerated]
			get
			{
				return this.A;
			}
			[CompilerGenerated]
			set
			{
				this.A = value;
			}
		}

		public int ReportThresholdCount
		{
			[CompilerGenerated]
			get
			{
				return A;
			}
			[CompilerGenerated]
			set
			{
				A = value;
			}
		}

		public int ReportThresholdWindowMinutes
		{
			[CompilerGenerated]
			get
			{
				return a;
			}
			[CompilerGenerated]
			set
			{
				a = value;
			}
		}
	}
	internal sealed class E
	{
		[CompilerGenerated]
		private bool A;

		[CompilerGenerated]
		private int A = 3;

		[CompilerGenerated]
		private int a = 1440;

		[CompilerGenerated]
		private int B = 72;

		public bool Enabled
		{
			[CompilerGenerated]
			get
			{
				return this.A;
			}
			[CompilerGenerated]
			set
			{
				this.A = value;
			}
		}

		public int ReportThresholdCount
		{
			[CompilerGenerated]
			get
			{
				return A;
			}
			[CompilerGenerated]
			set
			{
				A = value;
			}
		}

		public int ReportThresholdWindowMinutes
		{
			[CompilerGenerated]
			get
			{
				return a;
			}
			[CompilerGenerated]
			set
			{
				a = value;
			}
		}

		public int RecordHours
		{
			[CompilerGenerated]
			get
			{
				return B;
			}
			[CompilerGenerated]
			set
			{
				B = value;
			}
		}
	}
	internal sealed class e
	{
		[CompilerGenerated]
		private bool A;

		[CompilerGenerated]
		private int A = 10;

		[CompilerGenerated]
		private string A = "https://api.rustdemopro.com/uploads";

		[CompilerGenerated]
		private string a = "change-me";

		[CompilerGenerated]
		private string B = "https://rustdemopro.com";

		[CompilerGenerated]
		private F A = new F();

		public bool Enabled
		{
			[CompilerGenerated]
			get
			{
				return this.A;
			}
			[CompilerGenerated]
			set
			{
				this.A = value;
			}
		}

		public int OutboxMaxGB
		{
			[CompilerGenerated]
			get
			{
				return this.A;
			}
			[CompilerGenerated]
			set
			{
				this.A = value;
			}
		}

		public string Url
		{
			[CompilerGenerated]
			get
			{
				return this.A;
			}
			[CompilerGenerated]
			set
			{
				this.A = value;
			}
		}

		public string ApiKey
		{
			[CompilerGenerated]
			get
			{
				return a;
			}
			[CompilerGenerated]
			set
			{
				a = value;
			}
		}

		public string PortalBaseUrl
		{
			[CompilerGenerated]
			get
			{
				return B;
			}
			[CompilerGenerated]
			set
			{
				B = value;
			}
		}

		public F IncidentWebhook
		{
			[CompilerGenerated]
			get
			{
				return A;
			}
			[CompilerGenerated]
			set
			{
				A = value;
			}
		}
	}
	internal sealed class F
	{
		[CompilerGenerated]
		private string A = "";

		[CompilerGenerated]
		private int A = 5793266;

		[CompilerGenerated]
		private string a = "Demo Pro";

		[CompilerGenerated]
		private string B = "";

		[CompilerGenerated]
		private bool A = true;

		public string Url
		{
			[CompilerGenerated]
			get
			{
				return this.A;
			}
			[CompilerGenerated]
			set
			{
				this.A = value;
			}
		}

		public int Color
		{
			[CompilerGenerated]
			get
			{
				return this.A;
			}
			[CompilerGenerated]
			set
			{
				this.A = value;
			}
		}

		public string Username
		{
			[CompilerGenerated]
			get
			{
				return a;
			}
			[CompilerGenerated]
			set
			{
				a = value;
			}
		}

		public string AvatarUrl
		{
			[CompilerGenerated]
			get
			{
				return B;
			}
			[CompilerGenerated]
			set
			{
				B = value;
			}
		}

		public bool IncludeEventSummary
		{
			[CompilerGenerated]
			get
			{
				return A;
			}
			[CompilerGenerated]
			set
			{
				A = value;
			}
		}
	}
	internal sealed class f
	{
		private readonly Harmony m_A;

		private readonly List<G> m_A = new List<G>();

		private readonly Dictionary<string, g> m_A = new Dictionary<string, g>(StringComparer.OrdinalIgnoreCase);

		public IReadOnlyDictionary<string, g> Status => this.m_A;

		public f(string P_0)
		{
			//IL_0023: Unknown result type (might be due to invalid IL or missing references)
			//IL_002d: Expected O, but got Unknown
			this.m_A = new Harmony(P_0);
			this.m_A.Add(new R());
			this.m_A.Add(new S());
			this.m_A.Add(new r());
			this.m_A.Add(new U());
			this.m_A.Add(new t());
			this.m_A.Add(new T());
		}

		public void A()
		{
			this.m_A.Clear();
			foreach (G item in this.m_A)
			{
				try
				{
					item.A(this.m_A);
					this.m_A[item.Name] = g.A();
				}
				catch (Exception ex)
				{
					this.m_A[item.Name] = g.A(ex.GetType().Name + ": " + ex.Message);
				}
			}
		}

		public void a()
		{
			try
			{
				this.m_A.UnpatchAll(this.m_A.Id);
			}
			catch
			{
			}
		}

		public string A(string P_0)
		{
			StringBuilder stringBuilder = new StringBuilder(2048);
			stringBuilder.AppendLine(P_0 + " Patch Health");
			if (this.m_A.Count == 0)
			{
				stringBuilder.AppendLine("\t(no patches applied yet)");
				return stringBuilder.ToString();
			}
			foreach (KeyValuePair<string, g> item in this.m_A)
			{
				string key = item.Key;
				g value = item.Value;
				if (value.AppliedOk)
				{
					stringBuilder.AppendLine("\t" + key + ": Applied");
				}
				else
				{
					stringBuilder.AppendLine("\t" + key + ": FAILED - " + value.Error);
				}
			}
			return stringBuilder.ToString();
		}
	}
	internal interface G
	{
		string Name { get; }

		void A(Harmony P_0);
	}
	internal readonly struct g
	{
		[CompilerGenerated]
		private readonly bool m_A;

		[CompilerGenerated]
		private readonly string m_A;

		public bool AppliedOk
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
		}

		public string Error
		{
			[CompilerGenerated]
			get
			{
				return this.m_A;
			}
		}

		private g(bool P_0, string P_1)
		{
			this.m_A = P_0;
			this.m_A = P_1 ?? "";
		}

		public static g A()
		{
			return new g(true, "");
		}

		public static g A(string P_0)
		{
			return new g(false, P_0);
		}
	}
	internal sealed class R : G
	{
		public string Name => "Startup.Bootstrap.StartServer";

		public void A(Harmony P_0)
		{
			//IL_0065: Unknown result type (might be due to invalid IL or missing references)
			//IL_006b: Expected O, but got Unknown
			MethodInfo[] methods = typeof(Bootstrap).GetMethods(BindingFlags.Static | BindingFlags.Public | BindingFlags.NonPublic);
			MethodInfo methodInfo = null;
			foreach (MethodInfo methodInfo2 in methods)
			{
				if (string.Equals(methodInfo2.Name, "StartServer", StringComparison.Ordinal))
				{
					methodInfo = methodInfo2;
					break;
				}
			}
			if (methodInfo == null)
			{
				throw new MissingMethodException("Bootstrap.StartServer not found (signature changed?)");
			}
			HarmonyMethod val = new HarmonyMethod(new Action(Postfix).Method);
			P_0.Patch((MethodBase)methodInfo, (HarmonyMethod)null, val, (HarmonyMethod)null, (HarmonyMethod)null);
		}

		private static void Postfix()
		{
			try
			{
				C instance = SingletonComponent<C>.Instance;
				if (!((Object)(object)instance == (Object)null))
				{
					instance.C();
				}
			}
			catch
			{
			}
		}
	}
	internal sealed class r : G
	{
		public string Name => "Startup.PlayerDisconnected";

		public void A(Harmony P_0)
		{
			//IL_004f: Unknown result type (might be due to invalid IL or missing references)
			//IL_0055: Expected O, but got Unknown
			Type typeFromHandle = typeof(BasePlayer);
			MethodInfo methodInfo = typeFromHandle.GetMethod("OnDisconnected", BindingFlags.Instance | BindingFlags.Public) ?? typeFromHandle.GetMethod("OnDisconnected", BindingFlags.Instance | BindingFlags.NonPublic);
			if (methodInfo == null)
			{
				throw new MissingMethodException("BasePlayer.OnDisconnected not found (signature changed?)");
			}
			HarmonyMethod val = new HarmonyMethod(new Action<BasePlayer>(Postfix).Method);
			P_0.Patch((MethodBase)methodInfo, (HarmonyMethod)null, val, (HarmonyMethod)null, (HarmonyMethod)null);
		}

		private static void Postfix(BasePlayer __instance)
		{
			try
			{
				C instance = SingletonComponent<C>.Instance;
				if (!((Object)(object)instance == (Object)null))
				{
					instance.B(__instance);
				}
			}
			catch
			{
			}
		}
	}
	internal sealed class S : G
	{
		public string Name => "Startup.PlayerInit";

		public void A(Harmony P_0)
		{
			//IL_004f: Unknown result type (might be due to invalid IL or missing references)
			//IL_0055: Expected O, but got Unknown
			Type typeFromHandle = typeof(BasePlayer);
			MethodInfo methodInfo = typeFromHandle.GetMethod("PlayerInit", BindingFlags.Instance | BindingFlags.Public) ?? typeFromHandle.GetMethod("PlayerInit", BindingFlags.Instance | BindingFlags.NonPublic);
			if (methodInfo == null)
			{
				throw new MissingMethodException("BasePlayer.PlayerInit not found (signature changed?)");
			}
			HarmonyMethod val = new HarmonyMethod(new Action<BasePlayer>(Postfix).Method);
			P_0.Patch((MethodBase)methodInfo, (HarmonyMethod)null, val, (HarmonyMethod)null, (HarmonyMethod)null);
		}

		private static void Postfix(BasePlayer __instance)
		{
			try
			{
				C instance = SingletonComponent<C>.Instance;
				if (!((Object)(object)instance == (Object)null))
				{
					instance.a(__instance);
					instance.A(__instance);
				}
			}
			catch
			{
			}
		}
	}
	internal sealed class s : G
	{
		public string Name => "Reports.PlayerReported";

		public void A(Harmony P_0)
		{
			//IL_008f: Unknown result type (might be due to invalid IL or missing references)
			//IL_0095: Expected O, but got Unknown
			Type typeFromHandle = typeof(BasePlayer);
			MethodInfo methodInfo = null;
			MethodInfo[] methods = typeFromHandle.GetMethods(BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
			foreach (MethodInfo methodInfo2 in methods)
			{
				if (string.Equals(methodInfo2.Name, "OnPlayerReported", StringComparison.Ordinal))
				{
					ParameterInfo[] parameters = methodInfo2.GetParameters();
					if (parameters.Length == 1 && parameters[0].ParameterType.Name == "RPCMessage")
					{
						methodInfo = methodInfo2;
						break;
					}
				}
			}
			if (methodInfo == null)
			{
				throw new MissingMethodException("BasePlayer.OnPlayerReported(RPCMessage) not found (signature changed?)");
			}
			HarmonyMethod val = new HarmonyMethod(new Action<BasePlayer, RPCMessage>(Prefix).Method);
			P_0.Patch((MethodBase)methodInfo, val, (HarmonyMethod)null, (HarmonyMethod)null, (HarmonyMethod)null);
		}

		private static void Prefix(BasePlayer __instance, RPCMessage msg)
		{
			//IL_0013: Unknown result type (might be due to invalid IL or missing references)
			try
			{
				C instance = SingletonComponent<C>.Instance;
				if (!((Object)(object)instance == (Object)null))
				{
					instance.A(__instance, msg);
				}
			}
			catch
			{
			}
		}
	}
	internal sealed class T : G
	{
		public string Name => "Reports.RConBroadcastReport";

		public void A(Harmony P_0)
		{
			//IL_00b7: Unknown result type (might be due to invalid IL or missing references)
			//IL_00bd: Expected O, but got Unknown
			if (P_0 == null)
			{
				throw new ArgumentNullException("harmony");
			}
			MethodInfo methodInfo = null;
			MethodInfo[] methods = typeof(RCon).GetMethods(BindingFlags.Static | BindingFlags.Public | BindingFlags.NonPublic);
			foreach (MethodInfo methodInfo2 in methods)
			{
				if (string.Equals(methodInfo2.Name, "Broadcast", StringComparison.Ordinal))
				{
					ParameterInfo[] parameters = methodInfo2.GetParameters();
					if (parameters.Length == 2 && !(parameters[0].ParameterType != typeof(LogType)) && !(parameters[1].ParameterType != typeof(object)))
					{
						methodInfo = methodInfo2;
						break;
					}
				}
			}
			if (methodInfo == null)
			{
				throw new MissingMethodException("RCon.Broadcast(LogType, object) not found (signature changed?)");
			}
			HarmonyMethod val = new HarmonyMethod(new Action<LogType, object>(Prefix).Method);
			P_0.Patch((MethodBase)methodInfo, val, (HarmonyMethod)null, (HarmonyMethod)null, (HarmonyMethod)null);
		}

		private static void Prefix(LogType type, object obj)
		{
			//IL_0000: Unknown result type (might be due to invalid IL or missing references)
			//IL_0002: Invalid comparison between Unknown and I4
			try
			{
				if ((int)type != 4 || obj == null)
				{
					return;
				}
				C instance = SingletonComponent<C>.Instance;
				if (!((Object)(object)instance == (Object)null))
				{
					Type type2 = obj.GetType();
					string text = A(type2, obj, "PlayerId");
					string text2 = A(type2, obj, "PlayerName");
					string text3 = A(type2, obj, "TargetId");
					string text4 = A(type2, obj, "TargetName");
					string text5 = A(type2, obj, "Subject");
					string text6 = A(type2, obj, "Message");
					string text7 = A(type2, obj, "Type");
					if (!string.IsNullOrWhiteSpace(text3))
					{
						instance.A(text ?? "", text2 ?? "", text3 ?? "", text4 ?? "", text7 ?? "", text5 ?? "", text6 ?? "");
					}
				}
			}
			catch (Exception ex)
			{
				Debug.LogWarning((object)("[RustDemoPro] RConBroadcastReportPatch exception: " + ex.Message));
			}
		}

		private static string A(Type P_0, object P_1, string P_2)
		{
			try
			{
				if (P_1 is IDictionary<string, object> dictionary && dictionary.TryGetValue(P_2, out var value))
				{
					return value?.ToString();
				}
				if (P_1 is IDictionary dictionary2 && dictionary2.Contains(P_2))
				{
					return dictionary2[P_2]?.ToString();
				}
				Type type = P_1.GetType();
				if (type.FullName == "Newtonsoft.Json.Linq.JObject")
				{
					return (type.GetProperty("Item", new Type[1] { typeof(string) })?.GetValue(P_1, new object[1] { P_2 }))?.ToString();
				}
				PropertyInfo property = P_0.GetProperty(P_2, BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
				if (property != null)
				{
					return property.GetValue(P_1, null)?.ToString();
				}
				FieldInfo field = P_0.GetField(P_2, BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
				if (field != null)
				{
					return field.GetValue(P_1)?.ToString();
				}
			}
			catch
			{
			}
			return null;
		}
	}
	internal sealed class t : G
	{
		public string Name => "Gameplay.EntityDeath";

		public void A(Harmony P_0)
		{
			//IL_00d3: Unknown result type (might be due to invalid IL or missing references)
			//IL_00d9: Expected O, but got Unknown
			Type typeFromHandle = typeof(BaseCombatEntity);
			MethodInfo methodInfo = null;
			MethodInfo[] methods = typeFromHandle.GetMethods(BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
			foreach (MethodInfo methodInfo2 in methods)
			{
				if (string.Equals(methodInfo2.Name, "Die", StringComparison.Ordinal))
				{
					ParameterInfo[] parameters = methodInfo2.GetParameters();
					if (parameters.Length == 1 && parameters[0].ParameterType.Name == "HitInfo")
					{
						methodInfo = methodInfo2;
						break;
					}
				}
			}
			if (methodInfo == null)
			{
				foreach (MethodInfo methodInfo3 in methods)
				{
					if (string.Equals(methodInfo3.Name, "Die", StringComparison.Ordinal) && methodInfo3.GetParameters().Length == 0)
					{
						methodInfo = methodInfo3;
						break;
					}
				}
			}
			if (methodInfo == null)
			{
				throw new MissingMethodException("BaseCombatEntity.Die(...) not found (signature changed?)");
			}
			HarmonyMethod val = new HarmonyMethod(new Action<BaseCombatEntity, HitInfo>(Postfix).Method);
			P_0.Patch((MethodBase)methodInfo, (HarmonyMethod)null, val, (HarmonyMethod)null, (HarmonyMethod)null);
		}

		private static void Postfix(BaseCombatEntity __instance, HitInfo info)
		{
			try
			{
				C instance = SingletonComponent<C>.Instance;
				if (!((Object)(object)instance == (Object)null))
				{
					instance.a(__instance, info);
				}
			}
			catch
			{
			}
		}
	}
	internal sealed class U : G
	{
		public string Name => "Gameplay.EntityTakeDamage";

		public void A(Harmony P_0)
		{
			//IL_008f: Unknown result type (might be due to invalid IL or missing references)
			//IL_0095: Expected O, but got Unknown
			Type typeFromHandle = typeof(BaseCombatEntity);
			MethodInfo methodInfo = null;
			MethodInfo[] methods = typeFromHandle.GetMethods(BindingFlags.Instance | BindingFlags.Public | BindingFlags.NonPublic);
			foreach (MethodInfo methodInfo2 in methods)
			{
				if (string.Equals(methodInfo2.Name, "Hurt", StringComparison.Ordinal))
				{
					ParameterInfo[] parameters = methodInfo2.GetParameters();
					if (parameters.Length == 1 && parameters[0].ParameterType.Name == "HitInfo")
					{
						methodInfo = methodInfo2;
						break;
					}
				}
			}
			if (methodInfo == null)
			{
				throw new MissingMethodException("BaseCombatEntity.Hurt(HitInfo) not found (signature changed?)");
			}
			HarmonyMethod val = new HarmonyMethod(new Action<BaseCombatEntity, HitInfo>(Postfix).Method);
			P_0.Patch((MethodBase)methodInfo, (HarmonyMethod)null, val, (HarmonyMethod)null, (HarmonyMethod)null);
		}

		private static void Postfix(BaseCombatEntity __instance, HitInfo info)
		{
			try
			{
				C instance = SingletonComponent<C>.Instance;
				if (!((Object)(object)instance == (Object)null))
				{
					instance.A(__instance, info);
				}
			}
			catch
			{
			}
		}
	}
	internal class H
	{
		public string uploadKey;

		public string demoFile;

		public string startedUtc;

		public string endedUtc;

		public long totalBytes;
	}
	internal class h
	{
		public string uploadKey;

		public string serverId;

		public string steamId;

		public string archiveName;

		public bool compressed;

		public List<H> bundles = new List<H>();

		public k reportContext;

		public double? reportMarkerSeconds;
	}
	internal class I
	{
		public string A;

		public string a;

		public string B;

		public i A;

		public DateTime A;

		public DateTime a;

		public bool A;

		public HashSet<string> A = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
	}
	internal class i
	{
		public string plugin = "RustDemoPro";

		public string version = "0.2.1";

		public string serverIdentity;

		public string serverName;

		public string map;

		public int networkVersion;

		public ulong userId;

		public string steamId;

		public string playerName;

		public string demoPath;

		public string demoFileName;

		public string chunkReason;

		public string startedUtc;

		public string endedUtc;

		public string startedLocal;

		public string endedLocal;

		public double startedServerSeconds;

		public double endedServerSeconds;

		public double durationSeconds;

		public int chunkMinutes;

		public int eventCount;

		public int droppedEventCount;

		public k reportContext;
	}
	internal class J
	{
		public double serverSeconds;

		public string serverTimeLocal;

		public double chunkOffsetSeconds;

		public string type;

		public ulong attackerUserId;

		public ulong attackerEntityId;

		public string attackerName;

		public bool attackerIsNpc;

		public ulong targetUserId;

		public ulong targetEntityId;

		public string targetName;

		public bool targetIsNpc;

		public string weaponPrefab;

		public string weaponShortName;

		public string ammoPrefab;

		public string hitArea;

		public float distance;

		public float oldHp;

		public float predictedNewHp;

		public float damageTotal;

		public string info;
	}
	internal class j
	{
		public k A;

		public DateTime A;

		public DateTime a;
	}
	internal class K
	{
		public string A;

		public string a;

		public string B;

		public string b;

		public k A;

		public double? A;

		public int A;

		public DateTime A;

		public DateTime a;
	}
	internal class k
	{
		public string reportId;

		public ulong reporterUserId;

		public string reporterSteamId;

		public string reporterName;

		public ulong reportedUserId;

		public string reportedSteamId;

		public string reportedName;

		public string reason;

		public string subject;

		public string message;

		public string type;

		public string reportedAtUtc;

		public string captureWindowStartUtc;

		public string captureWindowEndUtc;

		public int captureWindowBeforeMinutes;

		public int captureWindowAfterMinutes;

		public double? reportMarkerSeconds;

		public int reportCount;

		public string reportedAtLocal;

		public string captureWindowStartLocal;

		public string captureWindowEndLocal;
	}
	internal class L
	{
		public ulong A;

		public k A;

		public DateTime A;

		public DateTime a;
	}
}
namespace RustDemoPro.HarmonyMod.Loader
{
	public sealed class RustDemoProLoader : IHarmonyModHooks
	{
		private static bool A;

		private static bool a;

		public void OnLoaded(OnHarmonyModLoadedArgs args)
		{
			if (!A)
			{
				A = true;
				C.a();
			}
		}

		public void OnUnloaded(OnHarmonyModUnloadedArgs args)
		{
			if (!a)
			{
				a = true;
				C.B();
			}
		}
	}
}
